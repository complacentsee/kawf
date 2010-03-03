<?php

require_once("printsubject.inc");
require_once("listthread.inc");
require_once("thread.inc");
require_once("filter.inc");
require_once("strip.inc");
require_once("textwrap.inc");	// for softbreaklongwords
require_once("message.inc");
require_once("postform.inc");
require_once("page-yatt.inc.php");

require_once("notices.inc");

$tpl->set_file(array(
  "showmessage" => "showmessage.tpl",
  "message" => "message.tpl",
  "forum_header" => array("forum/" . $forum['shortname'] . ".tpl", "forum/generic.tpl"),
));

message_set_block($tpl);

$tpl->set_var("FORUM_NAME", $forum['name']);
$tpl->set_var("FORUM_SHORTNAME", $forum['shortname']);

$tpl->set_var("FORUM_NOTICES", get_notices_html($forum, $user->aid));
$tpl->parse("FORUM_HEADER", "forum_header");

/* Grab the actual message */
$msg = fetch_message($user, $mid);

$index = find_msg_index($mid);
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
if ($msg['pmid'] != 0)
  $pmsg = fetch_message($user, $msg['pmid'], 'mid,subject,name' );

mark_thread_read($msg, $user);

/* generate message subjects in the thread this message is a part of */
$index = find_thread_index($msg['tid']);
$sql = "select *, UNIX_TIMESTAMP(tstamp) as unixtime from f_threads" . $indexes[$index]['iid'] . " where tid = '" . $msg['tid'] . "'";
$result = mysql_query($sql) or sql_error($sql);
$thread = mysql_fetch_array($result);

$options = explode(",", $thread['flags']);
foreach ($options as $name => $value)
  $thread["flag.$value"] = true;

/* UGLY hack, kludge, etc to workaround nasty ordering problem */
$_page = $tpl->get_var("PAGE");
unset($tpl->varkeys["PAGE"]);
unset($tpl->varvals["PAGE"]);
$tpl->set_var("PAGE", $_page);

$_domain = $tpl->get_var("DOMAIN");
unset($tpl->varkeys["DOMAIN"]);
unset($tpl->varvals["DOMAIN"]);
$tpl->set_var("DOMAIN", $_domain);

if (isset($pmsg)) {
  $tpl->set_var(array(
    "PMSG_MID" => $pmsg['mid'],
    "PMSG_SUBJECT" => softbreaklongwords($pmsg['subject'],40),
    "PMSG_NAME" => $pmsg['name'],
    "PMSG_DATE" => $pmsg['date'],
  ));
} else
  $tpl->set_var("parent", "");

render_message($tpl, $msg, $user, $uuser);	/* viewer, message owner */

$vmid = $msg['mid'];

list($messages, $tree, $path) = fetch_thread($thread, $vmid);

$threadmsg = "<ul class=\"thread\">\n";
$threadmsg .= list_thread(print_subject, $messages, $tree, reset($tree), $thread, $path);
$threadmsg .= "</ul>\n";

$tpl->set_var("THREAD", $threadmsg);

/* generate threadlinks */
if ($user->valid()) {
  if (isset($tthreads_by_tid[$msg['tid']])) {
    $threadlinks = "<a href=\"/" . $forum['shortname'] . "/untrack.phtml?tid=" . $thread['tid'] . "&amp;page=" . $script_name . $path_info . "&amp;token=" . $user->token() . "\" class=\"ut\" title=\"Untrack thread\">ut</a>";
  } else {
    $threadlinks = "<a href=\"/" . $forum['shortname'] . "/track.phtml?tid=" . $thread['tid'] . "&amp;page=" . $script_name . $path_info . "&amp;token=" . $user->token() . "\" class=\"tt\" title=\"Track thread\">tt</a>";
  }
} else
  $threadlinks = "";

if (isset($tthreads_by_tid[$msg['tid']]) &&
   ($thread['unixtime'] > $tthreads_by_tid[$msg['tid']]['unixtime'])) {
  $tpl->set_var("BGCOLOR", "#ccccee");
  if (count($messages) > 1)
    $threadlinks .= "<br><a href=\"/" . $forum['shortname'] . "/markuptodate.phtml?tid=" . $thread['tid'] . "&amp;page=" . $script_name . $path_info . "&amp;token=" . $user->token() . "&amp;time=" . time() . "\" class=\"up\" title=\"Update thread\">up</a>";
} else
  $tpl->set_var("BGCOLOR", "#eeeeee");

$tpl->set_var("THREADLINKS", $threadlinks);

/* create a new message based on current for postform */
$nmsg['msg'] = $nmsg['subject'] = $nmsg['urltext'] = $nmsg['video'] = "";
$nmsg['aid'] = $msg['aid'];
$nmsg['pmid'] = $msg['mid']; 	/* new pmid is current message */
$nmsg['tid'] = $msg['tid'];
$nmsg['ip'] = $remote_addr;

if (preg_match("/^Re:/i", $msg['subject'], $sregs))
  $nmsg['subject'] = $msg['subject'];
/*
else
  $nmsg['subject'] = "Re: " . $msg['subject'];
*/

render_postform($tpl, "post", $user, $nmsg);

$tpl->parse("MESSAGE", "message");

print generate_page($pmsg['subject'], $tpl->parse("CONTENT", "showmessage"));
?>
