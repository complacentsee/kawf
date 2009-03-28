<?php

require_once("listthread.inc");
require_once("filter.inc");
require_once("textwrap.inc");
require_once("notices.inc");

$tpl->set_file(array(
  "showthread" => "showthread.tpl",
  "message" => "message.tpl",
  "forum_header" => array("forum/" . $forum['shortname'] . ".tpl", "forum/generic.tpl"),
));

$tpl->set_block("message", "account_id", "_account_id");
$tpl->set_block("message", "forum_admin", "_forum_admin");
$tpl->set_block("message", "advertiser", "_advertiser");
$tpl->set_block("message", "sponsor", "_sponsor");
$tpl->set_block("message", "message_ip", "_message_ip");
$tpl->set_block("message", "reply", "_reply");
$tpl->set_block("message", "owner", "_owner");
$tpl->set_block("owner", "statelocked", "_statelocked");
$tpl->set_block("owner", "delete", "_delete");
$tpl->set_block("owner", "undelete", "_undelete");
$tpl->set_block("message", "parent", "_parent");
$tpl->set_block("message", "changes", "_changes");

$tpl->set_var("FORUM_NAME", $forum['name']);
$tpl->set_var("FORUM_SHORTNAME", $forum['shortname']);

$tpl->set_var("FORUM_NOTICES", get_notices_html($forum, $user->aid));
$tpl->parse("FORUM_HEADER", "forum_header");

/* Mark the thread as read if need be */
if (isset($tthreads[$msg['tid']]) &&
      $tthreads[$msg['tid']]['unixtime'] < $msg['unixtime']) {
  $sql = "update f_tracking set tstamp = NOW() where tid = " . $msg['tid'] . " and aid = " . $user->aid;
  mysql_query($sql) || sql_warn($sql);
}

if (isset($ad_generic)) {
  $urlroot = "/ads";
  /* We get our money from ads, make sure it's there */
  require_once("ads.inc");

  $ad = ads_view("$ad_generic,$ad_base_" . $forum['shortname'], "_top");
  $tpl->_set_var("AD", $ad);
}

$index = find_thread_index($tid);
$sql = "select * from f_threads" . $indexes[$index]['iid'] . " where tid = '" . addslashes($tid) . "'";
$result = mysql_query($sql) or sql_error($sql);

$thread = mysql_fetch_array($result);

$options = explode(",", $thread['flags']);
foreach ($options as $name => $value)
  $thread["flag.$value"] = true;

$index = find_msg_index($thread['mid']);
$tzoff=isset($user->tzoff)?$user->tzoff:0;
$sql = "select mid, tid, pid, aid, state, (UNIX_TIMESTAMP(date) - $tzoff) as unixtime, ip, subject, message, url, urltext, flags, name, email, views from f_messages" . $indexes[$index]['iid'] . " where tid = '" . $thread['tid'] . "' order by mid";
$result = mysql_query($sql) or sql_error($sql);
while ($message = mysql_fetch_array($result)) {
  $message['date'] = strftime("%Y-%m-%d %H:%M:%S", $message['unixtime']);
  $message['pmid'] = $message['pid'];
  $messages[] = $message;
}

$index++;
if (isset($indexes[$index])) {
  $sql = "select mid, tid, pid, aid, state, (UNIX_TIMESTAMP(date) - $tzoff) as unixtime, ip, subject, message, url, urltext, flags, name, email, views from f_messages" . $indexes[$index]['iid'] . " where tid = '" . $thread['tid'] . "' order by mid";
  $result = mysql_query($sql) or sql_error($sql);
  while ($message = mysql_fetch_array($result)) {
    $message['date'] = strftime("%Y-%m-%d %H:%M:%S", $message['unixtime']);
    $message['pmid'] = $message['pid'];
    $messages[] = $message;
  }
}

/* Filter out moderated or deleted messages, if necessary */
reset($messages);
while (list($key, $message) = each($messages)) {
  $tree[$message['mid']][] = $key;
  $tree[$message['pmid']][] = $key;
}

/* Walk down from the viewed message to the root to find the path */
/*
$pmid = $vmid;
do {
  $path[$pmid] = 'true';
  $key = reset($tree[$pmid]);
  $pmid = $messages[$key]['pmid'];
} while ($pmid);
*/

filter_messages($messages, $tree, reset($tree));

function print_message($thread, $msg)
{
  global $tpl, $user, $forum, $indexes;

  if (!empty($msg['flags'])) {
    $flagexp = explode(",", $msg['flags']);
    while (list(,$flag) = each($flagexp))
      $flags[$flag] = true;
  }

  $index = find_msg_index($msg['mid']);
  $sql = "update f_messages" . $indexes[$index]['iid'] . " set views = views + 1 where mid = '" . addslashes($msg['mid']) . "'";
  mysql_query($sql) or sql_warn($sql);

  $subject = "<a href=\"../msgs/" . $msg['mid'] . ".phtml\">" . softbreaklongwords($msg['subject'],40) . "</a>";
  $tpl->set_var(array(
    "MSG_SUBJECT" => $subject,
    "MSG_DATE" => $msg['date'],
    "MSG_MID" => $msg['mid'],
    "MSG_AID" => $msg['aid'],
  ));

  $uuser = new ForumUser;
  $uuser->find_by_aid((int)$msg['aid']);

  if ($user->valid() && !empty($msg['email'])) {
    /* Lame spamification */
    $email = preg_replace("/@/", "&#" . ord('@') . ";", $msg['email']);
    $tpl->set_var("MSG_NAMEEMAIL", "<a href=\"mailto:" . $email . "\">" . $msg['name'] . "</a>");
  } else
    $tpl->set_var("MSG_NAMEEMAIL", $msg['name']);

  $tpl->set_var("_parent", "");

  $message = nl2br($msg['message']);

  if (!empty($msg['url'])) {
    if (!empty($msg['urltext']))
      $message .= "<ul><li><a href=\"" . $msg['url'] . "\" target=\"_top\">" . $msg['urltext'] . "</a></ul>\n";
     else
      $message .= "<ul><li><a href=\"" . $msg['url'] . "\" target=\"_top\">" . $msg['url'] . "</a></ul>\n";
  }

  if (isset($flags['NewStyle']) && !isset($user->pref['HideSignatures']) &&
     isset($uuser->signature)) {
    if (!empty($uuser->signature))
      $message .= "<p>" . nl2br($uuser->signature) . "\n";
  }

  $tpl->set_var("MSG_MESSAGE", $message . "<br><br>\n");

  if ($user->capable($forum['fid'], 'Moderate')) {
    $tpl->set_var("MSG_AID", $msg['aid']);
    $changes = preg_replace("/&/", "&amp;", $msg['changes']);
    $changes = preg_replace("/</", "&lt;", $changes);
    $changes = preg_replace("/>/", "&gt;", $changes);
    $tpl->set_var("MSG_CHANGES", nl2br($changes));
    $tpl->set_var("MSG_IP", $msg['ip']);
    $tpl->set_var("MSG_EMAIL", $uuser->email);
    $tpl->parse("_changes", "changes");
    $tpl->parse("_message_ip", "message_ip");
  } else {
    $tpl->set_var("_changes", "");
    $tpl->set_var("_message_ip", "");
  }

  if ($user->capable($forum['fid'], 'Moderate') && $msg['aid'])
    $tpl->parse("_forum_admin", "forum_admin");
  else
    $tpl->set_var("_forum_admin", "");

  if ($uuser->capable($forum['fid'], 'Advertise'))
    $tpl->parse("_advertiser", "advertiser");
  else
    $tpl->set_var("_advertiser", "");

  if ($uuser->capable($forum['fid'], 'Sponsor'))
    $tpl->parse("_sponsor", "sponsor");
  else
    $tpl->set_var("_sponsor", "");

  if ($msg['aid'])
    $tpl->parse("_account_id", "account_id");
  else
    $tpl->set_var("_account_id", "");
/*
  if ($user->valid())
    $tpl->set_var("MSG_IP", $msg['ip']);
  else
    $tpl->set_var("message_ip", "");
*/

  if (!$user->valid() || $msg['aid'] == 0
    || (isset($thread['flag.Locked']) && !$user->capable($forum['fid'], 'Lock'))) {
    /* we're not allowed to do anything */
    $tpl->set_var("_reply", "");
    $tpl->set_var("_owner", "");
  } else if ($msg['aid'] != $user->aid) {
    /* we're only allowed to reply */
    $tpl->parse("_reply", "reply");
    $tpl->set_var("_owner", "");
  } else {
    if (isset($flags['StateLocked'])) {
      $tpl->set_var("_reply", "");
      $tpl->set_var("_undelete", "");
      if ($msg['state'] != 'OffTopic' && $msg['state'] != 'Active')
        $tpl->set_var("_delete", "");
      else
        $tpl->parse("_delete", "delete");

      $tpl->parse("_statelocked", "statelocked");
    } else {
      $tpl->parse("_reply", "reply");
      $tpl->set_var("_statelocked", "");
      if ($msg['state'] != 'Deleted') {
        $tpl->set_var("_undelete", "");
        $tpl->parse("_delete", "delete");
      } else {
        $tpl->set_var("_delete", "");
        $tpl->parse("_undelete", "undelete");
      }
    }

    $tpl->parse("_owner", "owner");
  }

  $tpl->parse("MESSAGE", "message");

  return $tpl->get_var("MESSAGE");
}

$messagestr = list_thread(print_message, $messages, $tree, reset($tree), $thread);

$tpl->set_var("MESSAGES", $messagestr);

$tpl->parse("HEADER", "header");
$tpl->parse("FOOTER", "footer");
$tpl->pparse("CONTENT", "showthread");
?>
