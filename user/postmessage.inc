<?php

/*
 * $msg passed by ref, it is altered as follows:
 *   sets $msg['mid'] to the new message id
 *   sets $msg['tid'] to the new thread id if there is no $msg['pmid'] (parent)
 *   sets $msg['flags'] to detected flags based on $request and msg content
 *   prepends $msg['imageurl'] to $msg['message'] if present
 *
 * returns true if ok, false if detected dup
*/

function postmessage($user, $fid, &$msg, $request)
{
  global $redis;
  $iid = last_iid();
  $mtable = "f_messages" . $iid;
  $ttable = "f_threads" . $iid;

  $track_thread = (isset($request['TrackThread']));
  $untrack_thread = (!isset($request['TrackThread']));

  if (isset($request['OffTopic']))
    $msg['state'] = "OffTopic";
  else
    $msg['state'] = "Active";

  $flags[] = "NewStyle";

  if (empty($msg['message']) && strlen($msg['message']) == 0)
    $flags[] = "NoText";

  if (!empty($msg['url']) || preg_match("/<[[:space:]]*a[[:space:]]+href/i", $msg['message']))
    $flags[] = "Link";

  if (!empty($msg['imageurl']) || preg_match("/<[[:space:]]*img[[:space:]]+src/i", $msg['message']))
    $flags[] = "Picture";

  if (!empty($msg['video']) || preg_match("/<[[:space:]]*video[[:space:]]+src/i", $msg['message']))
    $flags[] = "Video";

  $msg['flags'] = implode(",", $flags);

  /* IMAGEURL HACK - prepend before insert */
  /* for entry into the db */
  $msg = image_url_hack_insert($msg);

  /* Add it into the database */
  /* Check to make sure this isn't a duplicate */
  /* TZ: 'tstamp' is SQL server localtime */

    $redis->hSet($request['postcookie'],'fid',$fid);
    $redis->expire($request['postcookie'], 180);
    $redis->hSet($request['postcookie'],'aid',$user->aid);
    $redis->hSet($request['postcookie'],'ip',$msg['ip']);
    /* No exception, everything is ok, post */
    $newmessage = true;

    /* Cookie already exists in f_dupposts. Message might be a dup */
    /* Get mid for this cookie, if any */
    $msg['mid'] = $redis->hExists($request['postcookie'],'mid') ? $redis->hGet($request['postcookie'],'mid') : NULL;

    /* Issue #25 - if there is no mid for this cookie, the message wasn't posted yet,
     * so this message isn't a dup - it truly is new. Pretend nothing bad happened */
    if (!$msg['mid'])
	    $newmessage = true;  
    else
	    $newmessage = false;

  if(!$newmessage) {
    /* get old message state */
    $omsg = db_query_first("select state from $mtable where mid = ?", array($msg['mid']));

    /* update with new message */
    $sql = "update $mtable set " .
      "name = ?, email = ?, ip = ?, flags = ?, subject = ?, " .
      "message = ?, url = ?, urltext = ?, video = ?, state = ? " .
      "where mid = ? and state = 'Active'";
    db_exec($sql, array(
      $msg['name'], $msg['email'], $msg['ip'], $msg['flags'], $msg['subject'],
      $msg['message'], $msg['url'], $msg['urltext'], $msg['video'],
      $msg['state'], $msg['mid']
    ));

    /* unwind... do we really need these? */
    /* unwind index for old message */
    if ($omsg != null && !is_null($omsg['state'])) {
      if (!isset($msg['pmid'])){
        db_exec("update f_indexes set " . $omsg['state'] . " = " . $omsg['state'] . " - 1 where iid = ?", array($iid));
        $redis->hIncrBy('f_index_' . $fid, strtolower($omsg['state']) , -1);
      }
      /* unwind post count for old message */
      $user->post($fid, $omsg['state'], -1);
    }

    $track_thread = $untrack_thread = false;
  } else {
    /* New message */

    /* update postcookie with the new mid */
    // NEEDS TO BE MOVED LATER
    //db_exec("update f_dupposts set mid = ? where cookie = ?", array($msg['mid'], $request['postcookie']));

    /* add the message to the db */
    /* TZ: f_messagesXX 'date' is SQL server local time */
    /* FIXME: translate pid -> pmid */
    $sql = "insert into $mtable " .
	          "(aid, pid, tid, name, email, date, ip, flags, subject, message, url, urltext, video, state, views, changes ) "
            . "values (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 0, ? );";

    if ($msg["imagedeleteurl"]) {
      $msg["changes"] = "Image delete url for " . $msg["imageurl"] . " = " . $msg["imagedeleteurl"] . "\n";
    } else {
      $msg["changes"] = "";
    }

    $mid = db_query_transaction($sql, array(
      $user->aid, $msg['pmid'] ? $msg['pmid'] : 0, $msg['tid'] ? $msg['tid'] : 0, $msg['name'],
      $msg['email'], $msg['ip'], $msg['flags'], $msg['subject'],
      $msg['message'], $msg['url'], $msg['urltext'], $msg['video'],
      $msg['state'], $msg['changes']
    ));

    $msg['mid'] = $mid;
    $redis->hSet($request['postcookie'],'mid',$msg['mid']);


    if (isset(!$msg['pmid'])) {
      /* Message is a new thread, update thread table */
      /* TZ: f_threadsXX 'tstamp' is SQL server local time */
      $sql = "insert into $ttable (mid, tstamp, flags ) values ( ?, NOW(), '' )";
      $tid = db_query_transaction($sql, array($msg['mid']));
      $msg['tid'] = $tid;

      $sql = "update $mtable set tid = ? where mid = ?";
      db_exec($sql, array($msg['tid'], $msg['mid']));

    } else {
      if (!is_numeric($msg['tid']))
        throw new RuntimeException('no tid on followup to ' . $msg['pmid'] . ' for ' . $request['postcookie']);

      $tid = addslashes($msg['tid']);

      /* check to make sure we're not already tracking, or we're already 
	    not tracking */
      $row = db_query_first(
        "select count(*) from f_tracking where fid = ? and aid = ? and tid = ?",
        array($fid, $user->aid, $tid)
      );
      $count = $row ? $row[0] : NULL;


      /* override track/untrack accordingly */
      if ($count) $track_thread = false;
      else $untrack_thread = false;

      /* TZ: 'tstamp' is SQL server localtime */
      /* save current tstamp for track thread - bug 2969636 */
      if ($track_thread) {
        $row = db_query_first("select UNIX_TIMESTAMP(tstamp) from $ttable where tid = ?", array($tid));
        $tstamp = $row ? $row[0] : NULL;
      }
      $sql = "update $ttable set replies = replies + 1, tstamp = NOW() where tid = ?";
      db_exec($sql, array($tid));
    }
    
    foreach ($msg as $key => $value) {
      $redis->hSet($fid . '-' . $msg['mid'],$key,$value);
    }

    /* update indexes  */
    $sql = "update f_indexes set maxmid = ? ";

    if (!$msg['pmid']) {
      $status = $msg['state'];
      $sql .= ", $status = $status + 1, maxtid = ? ";
    }
    $sql .= "where iid = ?";

    if (!$msg['pmid']) {
      db_exec($sql, array($msg['mid'], $msg['tid'], $iid));
      $redis->hSet('f_index_' . $fid, 'maxtid', $msg['tid']);
      $redis->hIncrBy('f_index_' . $fid, strtolower($status), 1);
    } else {
      db_exec($sql, array($msg['mid'], $iid));
      $redis->hSet('f_index_' . $fid, 'maxmid', $msg['mid']);
    }

    /* bump post count for user */
    $user->post($fid, $msg['state'], 1);
  }

  $sql = "replace into f_updates ( fid, mid ) values ( ?, ? )";
  db_exec($sql, array($fid, $msg['mid']));

  if ($track_thread && is_numeric($msg['tid']))
    track_thread($fid, $msg['tid'], (isset($request['EmailFollowup']))?'SendEmail':'', $tstamp);

  if ($untrack_thread && is_numeric($msg['tid']))
    untrack_thread($fid, $msg['tid']);

  return $newmessage;
}
// vim:sw=2
?>

