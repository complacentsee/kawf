<?php

#if (file_exists("/data/search/sqldown.html")) {
#  Header("HTTP/1.0 500 Internal Server Error");

#  readfile("/data/search/sqldown.html");

#  exit;
#}

/* First setup the path */
$include_path = "$srcroot:$srcroot/lib:$srcroot/include:$srcroot/user:$srcroot/user/acl";
if (!isset($dont_use_account))
  $include_path .= ":" . "$srcroot/user/account";

if (isset($include_append))
  $include_path .= ":" . $include_append;

$old_include_path = ini_get("include_path");
if (!empty($old_include_path))
  $include_path .= ":" . $old_include_path;
ini_set("include_path", $include_path);

include_once("$config.inc");
require_once("sql.inc");
require_once("util.inc");
require_once("filter.inc");
require_once("forumuser.inc");
require_once("timezone.inc");
require_once("acl_ip_ban.inc");
require_once("acl_ip_ban_list.inc");

sql_open($database);
mysql_query("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");

$tpl = new Template($template_dir, "comment");

/* $_page saved off for others here for use in resused template that recurse,
   or for the set_var order sensitivity for vars within blocks */
$_page = $_REQUEST['page'];

$tpl->set_var("PAGE", $script_name . $path_info);
if (isset($http_host) && !empty($http_host))
  $_url = $http_host;
else {
  $_url = $server_name;

  if ($server_port != 80)
    $_url .= ":" . $server_port;
}
$tpl->set_var("URL", $_url . $script_name . $path_info);

/* Still needed for account templates */
if (isset($domain) && strlen($domain))
  $tpl->set_var("DOMAIN", $domain);

$scripts = array(
  "" => "index.php",

  "preferences.phtml" => "preferences.php",

  "tracking.phtml" => "tracking.php",
  "directory.phtml" => "directory.php",

  "redirect.phtml" => "redirect.php",
  "gmessage.phtml" => "gmessage.php",
);

/* If you have your own account management routines */
if (!isset($dont_use_account)) {
  $account_scripts = array(
    "login.phtml" => "account/login.php",
    "logout.phtml" => "account/logout.php",

    "forgotpassword.phtml" => "account/forgotpassword.php",

    "create.phtml" => "account/create.php",
    "acctedit.phtml" => "account/acctedit.php",
    "finish.phtml" => "account/finish.php",
    "f" => "account/finish.php",
  );

  foreach ($account_scripts as $virtual => $real)
    $scripts[$virtual] = $real;
}

$fscripts = array(
  "" => "showforum.php",

  "tracking.phtml" => "showtracking.php",

  "post.phtml" => "post.php",
  "edit.phtml" => "edit.php",
  "delete.phtml" => "delete.php",
  "undelete.phtml" => "undelete.php",

  "track.phtml" => "track.php",
  "untrack.phtml" => "untrack.php",
  "markuptodate.phtml" => "markuptodate.php",

  "lock.phtml" => "lock.php",
  "unlock.phtml" => "unlock.php",
  "changestate.phtml" => "changestate.php",
  "sticky.phtml" => "sticky.php",
);

header("Cache-Control: private");

$user = new ForumUser;
$user->find_by_cookie();

$IPBAN = AclIpBanList::find_matching_ban_list($_SERVER["REMOTE_ADDR"]);

function update_visits()
{
  global $user, $_SERVER;
  $ip = "'" . addslashes($_SERVER['REMOTE_ADDR']) . "'";
  $aid = -1;

  if ($user->valid())
    $aid = $user->aid;

  $sql = "insert into f_visits ( aid, ip ) values ( $aid, $ip ) on duplicate key update tstamp=NOW()";
  mysql_query($sql) or sql_error($sql);
}

function find_forum($shortname)
{
  global $user, $forum, $indexes, $tthreads, $tthreads_by_tid;

  $sql = "select * from f_forums where shortname = '" . addslashes($shortname) . "'";
  $result = mysql_query($sql) or sql_error($sql);

  if (mysql_num_rows($result))
    $forum = mysql_fetch_array($result);
  else
    return 0;

  /* Short circuit it here */
  if (isset($forum['version']) && $forum['version'] == 1) {
    echo "This forum is currently undergoing maintenance, please try back in a couple of minutes\n";
    exit;
  }

  $indexes = build_indexes($forum['fid']);
  list($tthreads, $tthreads_by_tid) = build_tthreads($forum['fid']);

  $options = explode(",", $forum['options']);
  foreach ($options as $value)
    $forum['option'][$value] = true;

  return 1;
}

function build_indexes($fid)
{
  $indexes = array();

  /* Grab all of the indexes for the forum */
  $sql = "select * from f_indexes where fid = $fid and ( minmid != 0 or minmid < maxmid ) order by iid";
  $result = mysql_query($sql) or sql_error($sql);

  /* build indexes shard id cache */
  while ($index = mysql_fetch_assoc($result))
    $indexes[] = $index;
  
  return $indexes;
}

function build_tthreads($fid)
{
  global $user;

  $tthreads = array();
  $tthreads_by_tid = array();

  /* build tthreads_by_tid thread tracking cache */
  if ($user->valid()) {
    /* TZ: unixtime is seconds since epoch */
    $result = sql_query("select *, UNIX_TIMESTAMP(tstamp) as unixtime from f_tracking where fid = $fid and aid = " . $user->aid . " order by tid desc");

    while ($tthread = mysql_fetch_assoc($result)) {
      $tid = $tthread['tid'];

      if ($tid<=0) continue;

      /* HACK: f_tracking is missing a uniq key. Ditch dupe entries */
      /* Hopefully won't happen if migration 20100314063313 is applied */
      if (isset($tthreads_by_tid[$tid])) {
	if ($tthread['unixtime'] > $tthreads_by_tid[$tid]['unixtime']) {
	  // echo "dup tracking entry for tid $tid, overwriting<br>\n";
	  /* Crap. This one is newer than existing entry. Rebuild all of
	   * $tthreads w/o any entries with this tid */
	  $new = array();
	  foreach ($tthreads as $t) {
	    if ($t['tid']!=$tthread['tid']) $new[]=$tthread;
	  }
	  $tthreads[] = $new; 
	} else {
	  // echo "dup tracking entry for tid $tid, ignoring<br>\n";
	  /* Throw it away. Don't add it to $tthreads_by_tid or $tthread */
	  continue;
	}
      }

      /* Throw away threads that we can't see */
      if (filter_thread($tid))
	continue;

      /* explode 'f_tracking' options set column */
      if (!empty($tthread['options'])) {
	$options = explode(',', $tthread['options']);
	foreach ($options as $v) {
	  $tthread['option'][$v]=true;
	}
      }

      $tthreads_by_tid[$tid] = $tthread;
      $tthreads[] = $tthread;
    }
  }
  return array($tthreads, $tthreads_by_tid);
}

function mid_to_iid($mid)
{
  global $indexes;

  $index = find_msg_index($mid);
  if (!isset($index)) return null;
  return $indexes[$index]['iid'];
}

function find_msg_index($mid)
{
  global $indexes;

  if (!isset($indexes) || !count($indexes)) {
    err_not_found("indexes cache is empty");
    exit;
  }

  foreach ($indexes as $k=>$v)
    if ($v['minmid'] <= $mid && $mid <= $v['maxmid']) return $k;

  return null;
}

function tid_to_iid($tid)
{
  global $indexes;

  $index = find_thread_index($tid);
  if (!isset($index)) return null;
  return $indexes[$index]['iid'];
}

function find_thread_index($tid)
{
  global $indexes;

  if (!isset($indexes) || !count($indexes)) {
    err_not_found("indexes cache is empty");
    exit;
  }

  foreach ($indexes as $k=>$v)
    if ($v['mintid'] <= $tid && $tid <= $v['maxtid']) return $k;

  return null;
}

/* Parse out the directory/filename */
if (preg_match("/^(\/)?([A-Za-z0-9\.]*)$/", $script_name.$path_info, $regs)) {
  if (!isset($scripts[$regs[2]])) {
    if (find_forum($regs[2])) {
      Header("Location: http://$server_name$script_name$path_info/");
      exit;
    } else
      err_not_found("Unknown script " . $regs[2]);
  }

  include_once($scripts[$regs[2]]);
} elseif (preg_match("/^\/([0-9a-zA-Z_.-]+)\/([0-9]+)\.phtml$/", $script_name.$path_info, $regs)) {
  if (isset($query_string) && !empty($query_string))
    Header("Location: msgs/" . $regs[2] . ".phtml?" . $query_string);
  else
    Header("Location: msgs/" . $regs[2] . ".phtml");
} elseif (preg_match("/^\/([0-9a-zA-Z_.-]+)\/page([0-9]+)\.phtml$/", $script_name.$path_info, $regs)) {
  if (isset($query_string) && !empty($query_string))
    Header("Location: pages/" . $regs[2] . ".phtml?" . $query_string);
  else
    Header("Location: pages/" . $regs[2] . ".phtml");
} elseif (preg_match("/^\/([0-9a-zA-Z_.-]+)\/([0-9a-zA-Z_.-]*)$/", $script_name.$path_info, $regs)) {
  if (!find_forum($regs[1]))
    err_not_found("Unknown forum " . $regs[1]);

  if (!isset($fscripts[$regs[2]]))
    err_not_found("Unknown script " . $regs[2]);

  include_once($fscripts[$regs[2] . ""]);
} else if (preg_match("/^\/([0-9a-zA-Z_.-]+)\/pages\/([0-9]+)\.phtml$/", $script_name.$path_info, $regs)) {
  if (!find_forum($regs[1]))
    err_not_found("Unknown forum " . $regs[1]);

  /* Now show that page */
  $curpage = $regs[2];
  require_once("showforum.php");
} else if (preg_match("/^\/([0-9a-zA-Z_.-]+)\/tracking\/([0-9]+)\.phtml$/", $script_name.$path_info, $regs)) {
  if (!find_forum($regs[1]))
    err_not_found("Unknown forum " . $regs[1]);

  /* Now show that page */
  $curpage = $regs[2];
  require_once("showtracking.php");
} else if (preg_match("/^\/([0-9a-zA-Z_.-]+)\/msgs\/([0-9]+)\.(phtml|txt)$/", $script_name.$path_info, $regs)) {
  if (!find_forum($regs[1]))
    err_not_found("Unknown forum " . $regs[1]);

  /* See if the message number is legitimate */
  $mid = $regs[2];
  $fmt = $regs[3];
  $index = find_msg_index($mid);
  if (isset($index)) {
    $sql = "select mid from f_messages" . $indexes[$index]['iid'] . " where mid = '" . addslashes($mid) . "'";
    if (!$user->capable($forum['fid'], 'Delete')) {
      $qual[] = "state != 'Deleted' ";
      if ($user->valid())
        $qual[] = "aid = " . $user->aid;
    }

    if (isset($qual))
      $sql .= " and ( " . implode(" or ", $qual) . " )";

    $result = mysql_query($sql) or sql_error($sql);
  }

  if (isset($result) && mysql_num_rows($result)) {
    if ($fmt=='phtml')
	require_once("showmessage.php");
    else
	require_once("plainmessage.php");
  } else
    err_not_found("Unknown message " . $mid . " in forum " . $forum['shortname']. "\n$sql");
} else if (preg_match("/^\/([0-9a-zA-Z_.-]+)\/threads\/([0-9]+)\.phtml$/", $script_name.$path_info, $regs)) {
  if (!find_forum($regs[1]))
    err_not_found("Unknown forum " . $regs[1]);

  /* See if the thread number is legitimate */
  $tid = $regs[2];
  $index = find_thread_index($tid);
  if (isset($index)) {
    $sql = "select tid from f_threads" . $indexes[$index]['iid'] . " where tid = '" . addslashes($tid) . "'";
    $result = mysql_query($sql) or sql_error($sql);
  }

  if (isset($result) && mysql_num_rows($result)) {
    require_once("showthread.php");
  } else
    err_not_found("Unknown thread " . $tid . " in forum " . $forum['shortname']);
} else
  err_not_found("Unknown path");


/* FIXME: This kills performance */
// update_visits();

sql_close();
// vim: sw=2
?>
