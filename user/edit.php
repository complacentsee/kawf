<?php

$user->req();

if ($user->status != 'Active') {
  echo "Your account isn't validated\n";
  exit;
}

$mid = $_REQUEST['mid'];

/* Check the data to make sure they entered stuff */
if (!isset($mid) || !isset($forum)) {
  /* Hmm, how did this happen? Redirect them back to the main page */
  Header("Location: http://$server_name$script_name$path_info/");
  exit;
}

require_once("strip.inc");
require_once("diff.inc");
require_once("message.inc");

$tpl->set_file(array(
  "edit" => "edit.tpl",
  "message" => "message.tpl",
  "forum_header" => array("forum/" . $forum['shortname'] . ".tpl", "forum/generic.tpl"),
));

$tpl->set_block("edit", "disabled");
$tpl->set_block("edit", "edit_locked");
$tpl->set_block("edit", "error");
$tpl->set_block("error", "image");
$tpl->set_block("error", "subject_req");
$tpl->set_block("error", "subject_too_long");
$tpl->set_block("edit", "preview");
$tpl->set_block("edit", "form");
$tpl->set_block("edit", "accept");

message_set_block($tpl);

$errors = array(
  "image",
  "subject_req",
  "subject_too_long",
);

if ($Debug) {
  $debug = "\n_REQUEST:\n";
  foreach ($_REQUEST as $k => $v) {
    if (!is_numeric($k) && strlen($v)>0)
      $debug.=" $k => $v\n";
  }
  $debug = str_replace("--","- -", $debug);
  $tpl->set_var("DEBUG", "<!-- $debug -->");
} else {
  $tpl->set_var("DEBUG", "");
}

$tpl->set_var("FORUM_NOTICES", "");
$tpl->parse("FORUM_HEADER", "forum_header");

$tpl->parse("HEADER", "header");
$tpl->parse("FOOTER", "footer");

$tpl->set_var("FORUM_NAME", $forum['name']);
$tpl->set_var("FORUM_SHORTNAME", $forum['shortname']);

/* UGLY hack, kludge, etc to workaround nasty ordering problem */
$_domain = $tpl->get_var("DOMAIN");
unset($tpl->varkeys["DOMAIN"]);
unset($tpl->varvals["DOMAIN"]);
$tpl->set_var("DOMAIN", $_domain);

$index = find_msg_index($mid);

$sql = "select * from f_messages" . $indexes[$index]['iid'] . " where mid = '" . addslashes($mid) . "'";
$result = mysql_query($sql) or sql_error($sql);

/* get existing message */
$nmsg = $msg = mysql_fetch_array($result);

/* pick up new remote_addr */
$nmsg['ip'] = $remote_addr;

if (!isset($msg)) {
  echo "No message with mid $mid\n";
  exit;
}

if ($msg['aid'] != $user->aid) {
  echo "This message does not belong to you!\n";
  exit;
}

if (!empty($msg['flags'])) {
  $flagexp = explode(",", $msg['flags']);
  while (list(,$flag) = each($flagexp))
    $flags[$flag] = true;
}

if ($_REQUEST['preview'])
    $preview = 1;

if ($_REQUEST['imgpreview'])
    $imgpreview = 1;

if (!isset($_REQUEST['message'])) {
  /* hit "edit" link */
  $preview = 1;

  /* extract imageurl, remove from message */
  if (preg_match("/^<center><img src=\"([^\"]+)\"><\/center><p>(.*)$/s", $nmsg['message'], $regs)) {
    $nmsg['imageurl'] = $regs[1];
    $nmsg['message'] = $regs[2];
  }
  /* Synthesize state based on the state of the existing message. */ 
  $exposeemail = !empty($msg['email']);
  $offtopic = ($msg['state'] == 'OffTopic');
} else {
  /* form summitted */

  /* handle subject */
  $nmsg['subject'] = stripcrap($_REQUEST['subject'], $subject_tags);

  /* handle message */
  $nmsg['message'] = $_REQUEST['message'];
  /* remove any imageurl (to be replaced by _REQUEST'd imageurl below) */
  if (preg_match("/^<center><img src=\"([^\"]+)\"><\/center><p>(.*)$/s", $nmsg['message'], $regs))
    $nmsg['message'] = $regs[2];
  $nmsg['message'] = stripcrap($nmsg['message'], $standard_tags);

  /* handle urls and imgs */
  $nmsg['url'] = stripcrapurl($_REQUEST['url']);
  $nmsg['urltext'] = stripcrap($_REQUEST['urltext']);
  $nmsg['imageurl'] = stripcrapurl($_REQUEST['imageurl']);

  $exposeemail = $_REQUEST['ExposeEmail'];
  $offtopic = $_REQUEST['OffTopic'];
}

if (isset($ad_generic)) {
  $urlroot = "/ads";
  /* We get our money from ads, make sure it's there */
  require_once("ads.inc");

  $ad = ads_view("$ad_generic,${ad_base}_" . $forum['shortname'], "_top");
  $tpl->_set_var("AD", $ad);
}

if (!isset($forum['opt.PostEdit'])) {
  $tpl->set_var(array(
    "edit_locked" => "",
    "error" => "",
    "preview" => "",
    "form" => "",
    "accept" => "",
  ));

  $tpl->pparse("CONTENT", "post");
  exit;
}

$tpl->set_var("disabled", "");

$index = find_thread_index($msg['tid']);
$sql = "select * from f_threads" . $indexes[$index]['iid'] . " where tid = '" . addslashes($msg['tid']) . "'";
$result = mysql_query($sql) or sql_error($sql);

$thread = mysql_fetch_array($result);

$options = explode(",", $thread['flags']);
foreach ($options as $name => $value)
  $thread["flag.$value"] = true;

if (isset($thread['flag.Locked']) && !$user->capable($forum['fid'], 'Lock')) {
  $tpl->set_var(array(
    "error" => "",
    "preview" => "",
    "form" => "",
    "accept" => "",
  ));

  $tpl->pparse("CONTENT", "post");
  exit;
}

$tpl->set_var("edit_locked", "");

/* Sanitize the strings */
$nmsg['name'] = stripcrap($user->name);
if ($exposeemail)
  $nmsg['email'] = stripcrap($user->email);
else
  $nmsg['email'] = "";

/* update offtopic status */
if ($msg['state'] == 'Active' && $offtopic)
  $nmsg['state'] = "OffTopic";
else
  $nmsg['state'] = $msg['state'];

if (empty($nmsg['subject']) && strlen($nmsg['subject']) == 0)
  $error["subject_req"] = true;

if (strlen($nmsg['subject']) > 100) {
  $error["subject_too_long"] = true;
  $nmsg['subject'] = substr($nmsg['subject'], 0, 100);
}

/* Strip any tags from the data */

if (!empty($nmsg['url']) && !preg_match("/^[a-z]+:\/\//i", $nmsg['url']))
  $nmsg['url'] = "http://".$nmsg['url'];

if (!empty($nmsg['imageurl']) && !preg_match("/^[a-z]+:\/\//i", $nmsg['imageurl']))
  $nmsg['imageurl'] = "http://".$nmsg['imageurl'];

/* first time around, there is an imageurl set, and the user
   did not preview, force the action to "preview" */
if (!empty($nmsg['imageurl']) && !isset($imgpreview))
  $preview = 1;

if ((isset($error) || isset($preview)) && !empty($nmsg['imageurl'])) {
  $error["image"] = true;
  $imgpreview = 1;
}

/* render new message */
render_message($tpl, $nmsg, $user);

if (!isset($preview))
  $tpl->set_var("preview", "");

$tpl->parse("PREVIEW", "message");

if (isset($error) || isset($preview)) {
  /* PREVIEW - edit */
  foreach ($errors as $n => $e) {
    if (!isset($error[$e]))
      $tpl->set_var($e, "");
  }

  /* generate post form for new message */
  require_once("postform.inc");
  render_postform($tpl, "edit", $user, $nmsg, $imgpreview);

  $tpl->set_var("accept", "");
} else {
  /* POST */
  $tpl->set_var(array(
    "error" => "",
    "form" => "",
  ));

  /* overwrite with latest email record */
  if (!empty($nmsg['email']))
    $nmsg['email'] = $user->email;

  /* compose new set of flags */
  $flagset[] = "NewStyle";

  if (isset($flags['StateLocked']))
    $flagset[] = 'StateLocked';

  if (empty($nmsg['message']) && strlen($nmsg['message']) == 0)
    $flagset[] = "NoText";

  if (!empty($nmsg['url']) || preg_match("/<[[:space:]]*a[[:space:]]+href/i", $nmsg['message']))
    $flagset[] = "Link";

  if (!empty($nmsg['imageurl']) || preg_match("/<[[:space:]]*img[[:space:]]+src/i", $nmsg['message']))
    $flagset[] = "Picture";

  $flagset = implode(",", $flagset);

  /* prepend new imageurl for diffing and for entry into the db */
  if (!empty($nmsg['imageurl']))
    $nmsg['message'] = "<center><img src=\"" . $nmsg['imageurl']. "\"></center><p>\n" . $nmsg['message'];

  /* Create a diff for the old message and the new message */

  /* Dump the \r's, we don't want them */
  $msg['message'] = preg_replace("/\r/", "", $msg['message']);
  $nmsg['message'] = preg_replace("/\r/", "", $nmsg['message']);

  $old[]="Subject: " . $msg['subject'];
  $old = array_merge($old, explode("\n", $msg['message']));
  if (!empty($msg['url'])) {
    $old[]="urltext: " . $msg['urltext'];
    $old[]="url: " . $msg['url'];
  }

  $new[]="Subject: " . $nmsg['subject'];
  $new = array_merge($new, explode("\n", $nmsg['message']));
  if (!empty($nmsg['url'])) {
    $new[]="urltext: " . $nmsg['urltext'];
    $new[]="url: " . $nmsg['url'];
  }

  $diff = diff($old, $new);

  /* Add it into the database */
  $sql = "update f_messages" . $indexes[$index]['iid'] . " set " .
	"name = '" . addslashes($nmsg['name']) . "', " .
	"email = '" . addslashes($nmsg['email']) . "', " .
	"flags = '$flagset', " .
	"state = '" . addslashes($nmsg['state']) . "', " .
	"subject = '" . addslashes($nmsg['subject']) . "', " .
	"message = '" . addslashes($nmsg['message']) . "', " .
	"url = '" . addslashes($nmsg['url']) . "', " .
	"urltext = '" . addslashes($nmsg['urltext']) . "', " .
	"changes = CONCAT(changes, 'Edited by " . addslashes($user->name) . "/" . $user->aid . " at ', NOW(), ' from $remote_addr\n" . addslashes($diff) . "\n') " .
	"where mid = '" . addslashes($mid) . "'";
  mysql_query($sql) or sql_error($sql);

  $sql = "insert into f_updates ( fid, mid ) values ( " . $forum['fid'] . ", '" . addslashes($mid) . "' )";
  mysql_query($sql); 

  $tpl->set_var("MSG_MID", $mid);
}

$tpl->pparse("CONTENT", "edit");
?>
