<?php

$user->req();

require_once("printcollapsed.inc");
require_once("printsubject.inc");
require_once("listthread.inc");
require_once("filter.inc");
require_once("thread.inc");

$tpl->set_file("tracking", "tracking.tpl");

if (isset($user->pref['SimpleHTML'])) {
  $tpl->set_block("tracking", "normal");
  $tpl->set_block("tracking", "simple", "_block");
  $table_block = "simple";

  $tpl->set_var("normal", "");
} else {
  $tpl->set_block("tracking", "simple");
  $tpl->set_block("tracking", "normal", "_block");
  $table_block = "normal";

  $tpl->set_var("simple", "");
}

$tpl->set_block($table_block, "row", "_row");
$tpl->set_block($table_block, "update_all", "_update_all");
$tpl->set_var("USER_TOKEN", $user->token());

/* HACK */ 
$_page = $tpl->get_var("PAGE");
unset($tpl->varkeys["PAGE"]);
unset($tpl->varvals["PAGE"]);
$tpl->set_var("PAGE", $_page);

if (isset($ad_generic)) {
  $urlroot = "/ads";
  /* We get our money from ads, make sure it's there */
  require_once("ads.inc");

  $ad = ads_view($ad_generic, "_top");
  $tpl->_set_var("AD", $ad);
}

$time = time();
$tpl->set_var("TIME", $time);

function display_thread($thread)
{
  global $user, $forum, $ulkludge;

  $options = explode(",", $thread['flags']);
  foreach ($options as $name => $value)
    $thread["flag.$value"] = true;

  list($messages, $tree) = fetch_thread($thread);
  if (!isset($messages) || !count($messages))
    return array(0, "");

  $count = count($messages);

  if (isset($user->pref['Collapsed']))
    $messagestr = print_collapsed($thread, reset($messages), $count - 1);
  else
    $messagestr = list_thread(print_subject, $messages, $tree, reset($tree), $thread);

  if (empty($messagestr))
    return array(0, "");

  if (!$ulkludge || isset($user->pref['SimpleHTML']))
    $messagestr .= "</ul>";

  return array($count, "<ul class=\"thread\">\n" . $messagestr);
}

$sql = "select * from f_forums order by fid";
$result = mysql_query($sql) or sql_error($sql);

$numshown = 0;

$tzoff=isset($user->tzoff)?$user->tzoff:0;
while ($forum = mysql_fetch_array($result)) {
  $tpl->set_var("FORUM_NAME", $forum['name']);
  $tpl->set_var("FORUM_SHORTNAME", $forum['shortname']);

  unset($indexes);

  $sql = "select * from f_indexes where fid = " . $forum['fid'];
  $res2 = mysql_query($sql) or sql_error($sql);

  $numindexes = mysql_num_rows($res2);

  for ($i = 0; $i < $numindexes; $i++)
    $indexes[$i] = mysql_fetch_array($res2);

  $sql = "select *, (UNIX_TIMESTAMP(tstamp) - $tzoff) as unixtime from f_tracking where fid = " . $forum['fid'] . " and aid = " . $user->aid . " order by tid desc";
  $res2 = mysql_query($sql) or sql_error($sql);

  $forumcount = $forumupdated = 0;

  unset($tthreads_by_tid);

  $tpl->set_var("_row", "");

  while ($tthread = mysql_fetch_array($res2)) {
    $tthreads_by_tid[$tthread['tid']] = $tthread;

    $index = find_thread_index($tthread['tid']);
    $thread = sql_querya("select *, (UNIX_TIMESTAMP(tstamp) - $tzoff) as unixtime from f_threads" . $indexes[$index]['iid'] . " where tid = '" . addslashes($tthread['tid']) . "'");
    if (!$thread)
      continue;

    list($count, $messagestr) = display_thread($thread);

    if (!$count)
      continue;

    if ($thread['unixtime'] > $tthread['unixtime']) {
      $tpl->set_var("CLASS", "trow" . ($forumcount % 2));
      $forumupdated++;
    } else
      $tpl->set_var("CLASS", "row" . ($forumcount % 2));

    $forumcount++;
    $numshown++;

    /* If the thread is tracked, we know they are a user already */
    $messagelinks = "<a href=\"/" . $forum['shortname'] . "/untrack.phtml?tid=" . $thread['tid'] . "&amp;page=" . $script_name . $path_info . "&amp;token=" . $user->token() . "\"><font color=\"#d00000\">ut</font></a>";
    if ($count > 1) {
      if (!isset($user->pref['Collapsed']))
        $messagelinks .= "<br>";
      else
        $messagelinks .= " ";

      if ($thread['unixtime'] > $tthread['unixtime'])
        $messagelinks .= "<a href=\"/" . $forum['shortname'] . "/markuptodate.phtml?tid=" . $thread['tid'] . "&amp;page=" . $script_name . $path_info . "&amp;token=" . $user->token() . "&amp;time=$time\"><font color=\"#0000f0\">up</font></a>";
    }

    $tpl->set_var("MESSAGES", $messagestr);
    $tpl->set_var("MESSAGELINKS", $messagelinks);

    $tpl->parse("_row", "row", true);
  }

  if ($forumupdated)
    $tpl->parse("_update_all", "update_all");
  else
    $tpl->set_var("_update_all", "");

  if ($forumcount) {
    /* HACK: ugly */
    unset($tpl->varkeys['forum_header']);
    unset($tpl->varvals['forum_header']);

    $tpl->set_file("forum_header",
	array("forum/" . $forum['shortname'] . ".tpl", "forum/generic.tpl"));

    $tpl->set_var("FORUM_NOTICES", "");
    $tpl->parse("FORUM_HEADER", "forum_header");

    $tpl->parse("_block", $table_block, true);
  }
}

if (!$numshown)
  $tpl->set_var("_block", "<font size=\"+1\">No updated threads</font><br>");

$tpl->set_var("token", $user->token());

$tpl->parse("HEADER", "header");
$tpl->parse("FOOTER", "footer");
$tpl->pparse("CONTENT", "tracking");
?>
