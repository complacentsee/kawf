<?php

require_once("printsubject.inc");
require_once("listthread.inc");
require_once("thread.inc");
require_once("filter.inc");
require_once("strip.inc");
require_once("textwrap.inc");	// for softbreaklongwords
require_once("notices.inc");

$tpl->set_file(array(
  "showmessage" => "showmessage.tpl",
  "message" => "message.tpl",
  "forum_header" => array("forum/" . $forum['shortname'] . ".tpl", "forum/generic.tpl"),
));

$tpl->set_block("message", "account_id");
$tpl->set_block("message", "forum_admin");
$tpl->set_block("message", "advertiser");
$tpl->set_block("message", "sponsor");
$tpl->set_block("message", "message_ip");
$tpl->set_block("message", "reply");
$tpl->set_block("message", "owner");
$tpl->set_block("owner", "statelocked");
$tpl->set_block("owner", "delete");
$tpl->set_block("owner", "undelete");
$tpl->set_block("message", "parent");
$tpl->set_block("message", "changes");

$tpl->set_var("FORUM_NAME", $forum['name']);
$tpl->set_var("FORUM_SHORTNAME", $forum['shortname']);

$tpl->set_var("FORUM_NOTICES", get_notices_html($forum, $user->aid));
$tpl->parse("FORUM_HEADER", "forum_header");

/* Grab the actual message */
$index = find_msg_index($mid);
$tzoff=isset($user->tzoff)?$user->tzoff:0;
$sql = "select *, (UNIX_TIMESTAMP(date) - $tzoff) as unixtime from f_messages" . $indexes[$index]['iid'] . " where mid = '" . addslashes($mid) . "'";
$result = mysql_query($sql) or sql_error($sql);

$msg = mysql_fetch_array($result);

$msg['date'] = strftime("%Y-%m-%d %H:%M:%S", $msg['unixtime']);

$sql = "update f_messages" . $indexes[$index]['iid'] . " set views = views + 1 where mid = '" . addslashes($mid) . "'";
mysql_query($sql) or sql_warn($sql);

if (!empty($msg['flags'])) {
  $flagexp = explode(",", $msg['flags']);
  while (list(,$flag) = each($flagexp))
    $flags[$flag] = true;
}

$uuser = new ForumUser;
$uuser->find_by_aid((int)$msg['aid']);

/* Grab some information about the parent (if there is one) */
if (!isset($msg['pmid']))
  $msg['pmid'] = $msg['pid'];

if ($msg['pmid'] != 0) {
  $index = find_msg_index($msg['pmid']);
  $sql = "select mid, subject, name, (UNIX_TIMESTAMP(date) - $tzoff) as unixtime from f_messages" . $indexes[$index]['iid'] . " where mid = " . $msg['pmid'];
  $result = mysql_query($sql) or sql_error($sql);

  $pmsg = mysql_fetch_array($result);
  $pmsg['date'] = strftime("%Y-%m-%d %H:%M:%S", $pmsg['unixtime']);
}

/* Mark the thread as read if need be */
if (isset($tthreads_by_tid[$msg['tid']]) &&
    $tthreads_by_tid[$msg['tid']]['unixtime'] < $msg['unixtime']) {
  $sql = "update f_tracking set tstamp = NOW() where fid = " . $forum['fid'] . " and tid = " . $msg['tid'] . " and aid = " . $user->aid;
  mysql_query($sql) or sql_warn($sql);
}

$index = find_thread_index($msg['tid']);
$sql = "select *, UNIX_TIMESTAMP(tstamp) as unixtime from f_threads" . $indexes[$index]['iid'] . " where tid = '" . $msg['tid'] . "'";
$result = mysql_query($sql) or sql_error($sql);
$thread = mysql_fetch_array($result);

$options = explode(",", $thread['flags']);
foreach ($options as $name => $value)
  $thread["flag.$value"] = true;

if (isset($ad_generic)) {
  $urlroot = "/ads";
  /* We get our money from ads, make sure it's there */
  require_once("ads.inc");

  $ad = ads_view("$ad_generic,${ad_base}_" . $forum['shortname'], "_top");
  $tpl->_set_var("AD", $ad);
}

if ($user->capable($forum['fid'], 'Moderate')) {
  $tpl->set_var("MSG_IP", $msg['ip']);
  $tpl->set_var("MSG_EMAIL", $uuser->email);
  $msg['changes'] = trim($msg['changes']);
  if(strlen($msg['changes'])>0) {
      // TODO; use diff highlight?
      $changes = preg_replace("/&/", "&amp;", $msg['changes']);
      $changes = preg_replace("/</", "&lt;", $changes);
      $changes = preg_replace("/>/", "&gt;", $changes);
      $tpl->set_var("MSG_CHANGES", nl2br($changes));
  } else {
      $tpl->set_var("changes", "");
  }
} else {
  $tpl->set_var("changes", "");
  $tpl->set_var("message_ip", "");
}

if (!$user->capable($forum['fid'], 'Moderate') || !$msg['aid'])
  $tpl->set_var("forum_admin", "");
if (!$uuser->capable($forum['fid'], 'Advertise'))
  $tpl->set_var("advertiser", "");
if (!$uuser->capable($forum['fid'], 'Sponsor'))
  $tpl->set_var("sponsor", "");

if (!$msg['aid'])
  $tpl->set_var("account_id", "");

/*
if ($user->valid())
  $tpl->set_var("MSG_IP", $msg['ip']);
else
  $tpl->set_var("message_ip", "");
*/

if (!$user->valid() || $msg['aid'] == 0
  || (isset($thread['flag.Locked']) && !$user->capable($forum['fid'], 'Lock'))) {
  $tpl->set_var("reply", "");
  $tpl->set_var("owner", "");
} else if ($msg['aid'] != $user->aid) {
  /* we're only allowed to reply */
  $tpl->set_var("owner", "");
} else {
  if (isset($flags['StateLocked'])) {
    $tpl->set_var("reply", "");
    $tpl->set_var("undelete", "");
    if ($msg['state'] != 'OffTopic' && $msg['state'] != 'Active')
      $tpl->set_var("delete", "");
  } else {
    $tpl->set_var("statelocked", "");
    if ($msg['state'] != 'Deleted')
      $tpl->set_var("undelete", "");
    else
      $tpl->set_var("delete", "");
  }
}

$tpl->set_var(array(
  "TMSG_SUBJECT" => $msg['subject'],
  "MSG_SUBJECT" => softbreaklongwords($msg['subject'],40),
  "MSG_DATE" => $msg['date'],
  "MSG_MID" => $msg['mid'],
  "MSG_TID" => $msg['tid'],
  "MSG_AID" => $msg['aid'],
));

/* UGLY hack, kludge, etc to workaround nasty ordering problem */
$_page = $tpl->get_var("PAGE");
unset($tpl->varkeys["PAGE"]);
unset($tpl->varvals["PAGE"]);
$tpl->set_var("PAGE", $_page);

$_domain = $tpl->get_var("DOMAIN");
unset($tpl->varkeys["DOMAIN"]);
unset($tpl->varvals["DOMAIN"]);
$tpl->set_var("DOMAIN", $_domain);

if ($user->valid() && !empty($msg['email'])) {
  /* Lame spamification */
  $email = preg_replace("/@/", "&#" . ord('@') . ";", $msg['email']);
  $tpl->set_var("MSG_NAMEEMAIL", "<a href=\"mailto:" . $email . "\">" . $msg['name'] . "</a>");
} else
  $tpl->set_var("MSG_NAMEEMAIL", $msg['name']);

// parent message
if (isset($pmsg)) {
  $tpl->set_var(array(
    "PMSG_MID" => $pmsg['mid'],
    "PMSG_SUBJECT" => softbreaklongwords($pmsg['subject'],40),
    "PMSG_NAME" => $pmsg['name'],
    "PMSG_DATE" => $pmsg['date'],
  ));
} else
  $tpl->set_var("parent", "");

$message = nl2br(softbreaklongwords($msg['message'],78));
// $message = nl2br($msg['message']);

if (!empty($msg['url'])) {
  $urlset = 1;
  if (!empty($msg['urltext']))
    $message .= "<ul><li><a href=\"" . $msg['url'] . "\" target=\"_top\">" . $msg['urltext'] . "</a></ul>\n";
   else
    $message .= "<ul><li><a href=\"" . $msg['url'] . "\" target=\"_top\">" . $msg['url'] . "</a></ul>\n";
}

if (isset($flags['NewStyle']) && !isset($user->pref['HideSignatures']) &&
   isset($uuser->signature)) {
  unset($urlset);
  if (!empty($uuser->signature))
    $message .= "<p>" . nl2br($uuser->signature) . "\n";
}

if (!isset($urlset))
  $message .= "<br>";

$tpl->set_var("MSG_MESSAGE", $message . "<br>\n");

$vmid = $msg['mid'];

list($messages, $tree, $path) = fetch_thread($thread, $vmid);

$threadmsg = "<ul class=\"thread\">\n";
$threadmsg .= list_thread(print_subject, $messages, $tree, reset($tree), $thread, $path);
if (!$ulkludge)
  $threadmsg .= "</ul>\n";

$tpl->set_var("THREAD", $threadmsg);

if ($user->valid()) {
  if (isset($tthreads_by_tid[$msg['tid']])) {
    $threadlinks = "<a href=\"/" . $forum['shortname'] . "/untrack.phtml?tid=" . $thread['tid'] . "&page=" . $script_name . $path_info . "&token=" . $user->token() . "\"><font color=\"#d00000\">ut</font></a>";
  } else {
    $threadlinks = "<a href=\"/" . $forum['shortname'] . "/track.phtml?tid=" . $thread['tid'] . "&page=" . $script_name . $path_info . "&token=" . $user->token() . "\"><font color=\"#00d000\">tt</font></a>";
  }
} else
  $threadlinks = "";

if (isset($tthreads_by_tid[$msg['tid']]) &&
   ($thread['unixtime'] > $tthreads_by_tid[$msg['tid']]['unixtime'])) {
  $tpl->set_var("BGCOLOR", "#ccccee");
  if (count($messages) > 1)
    $threadlinks .= "<br><a href=\"/" . $forum['shortname'] . "/markuptodate.phtml?tid=" . $thread['tid'] . "&page=" . $script_name . $path_info . "&token=" . $user->token() . "&time=" . time() . "\"><font color=\"#0000f0\">up</font></a>";
} else
  $tpl->set_var("BGCOLOR", "#eeeeee");

$tpl->set_var("THREADLINKS", $threadlinks);

$action = "post";

if (!preg_match("/^Re:/i", $msg['subject'], $sregs))
  // screw this, lets not let noobs be lame
  $subject = ""; // "Re: " . $msg['subject'];
 else
  $subject = $msg['subject'];

$pmid = $msg['mid'];
$tid = $msg['tid'];
unset($mid);
unset($message);

$parent = $msg;

require_once("post.inc");

$tpl->parse("MESSAGE", "message");

$tpl->parse("HEADER", "header");
$tpl->parse("FOOTER", "footer");
$tpl->pparse("CONTENT", "showmessage");
?>
