<?php

require_once("printsubject.inc");
require_once("listthread.inc");
require_once("filter.inc");
require_once("thread.inc");
require_once("textwrap.inc");
require_once("notices.inc");
require_once("page-yatt.inc.php");

$tpl->set_file(array(
  "showforum" => "showforum.tpl",
  "forum_header" => array("forum/" . $forum['shortname'] . ".tpl","forum/generic.tpl"),
));

$tpl->set_block("showforum", "restore_gmsgs");
$tpl->set_block("showforum", "update_all");
$tpl->set_block("showforum", "simple");
$tpl->set_block("showforum", "normal");

if (isset($user->pref['SimpleHTML'])) {
  $table_block = "simple";
  $tpl->set_var("normal", "");
} else {
  $table_block = "normal";
  $tpl->set_var("simple", "");
}

$tpl->set_block($table_block, "row", "_row");
$tpl->set_var("USER_TOKEN", $user->token());

/* UGLY hack, kludge, etc to workaround nasty ordering problem */
$_page = $tpl->get_var("PAGE");
unset($tpl->varkeys["PAGE"]);
unset($tpl->varvals["PAGE"]);
$tpl->set_var("PAGE", $_page);

/* Default it to the first page if none is specified */
if (!isset($curpage))
  $curpage = 1;

/* Number of threads per page we're gonna list */
if ($user->valid())
  $threadsperpage = $user->threadsperpage;
else
  $threadsperpage = 50;

if (!$threadsperpage)
  $threadsperpage = 50;

function threads($key)
{
  global $user, $forum, $indexes;

  $numthreads = $indexes[$key]['active'];

  /* People with moderate privs automatically see all moderated and deleted */
  /*  messages */
  if (isset($user->pref['ShowModerated']))
    $numthreads += $indexes[$key]['moderated'];

  if (isset($user->pref['ShowOffTopic']))
    $numthreads += $indexes[$key]['offtopic'];

  if ($user->capable($forum['fid'], 'Delete'))
    $numthreads += $indexes[$key]['deleted'];

  return $numthreads;
}

$tpl->set_var("FORUM_SHORTNAME", $forum['shortname']);

$tpl->set_var("FORUM_NOTICES", get_notices_html($forum, $user->aid));
$tpl->parse("FORUM_HEADER", "forum_header");

/* Figure out how many total threads the user can see */
$numthreads = 0;

reset($indexes);
while (list($key) = each($indexes))
  $numthreads += threads($key);

$numpages = ceil($numthreads / $threadsperpage);

$startpage = $curpage - 4;
if ($startpage < 1)
  $startpage = 1;

$endpage = $startpage + 9;
if ($endpage > $numpages)
  $endpage = $numpages;

/*
if ($endpage == $startpage)
  $endpage++;
*/

$pagestr = "";

if ($curpage > 1) {
  $prevpage = $curpage - 1;
  $pagestr .= "<a href=\"/" . $forum['shortname'] . "/pages/$prevpage.phtml\">&lt;&lt;&lt;</a> | ";
}

$pagestr .= "<a href=\"/" . $forum['shortname'] . "/pages/1.phtml\">";
if ($curpage == 1)
  $pagestr .= "<font size=\"+1\"><b>1</b></font>";
else
  $pagestr .= "1";
$pagestr .= "</a>";

if ($startpage == 1)
  $startpage++;
elseif ($startpage < $endpage)
  $pagestr .= " ... ";

for ($i = $startpage; $i <= $endpage; $i++) {
  $pagestr .= " | <a href=\"/" . $forum['shortname'] . "/pages/" . $i . ".phtml\">";
  if ($i == $curpage)
    $pagestr .= "<font size=\"+1\"><b>$i</b></font>";
  else
    $pagestr .= $i;
  $pagestr .= "</a>";
}

if ($curpage < $numpages) {
  $nextpage = $curpage + 1;
  $pagestr .= " | <a href=\"/" . $forum['shortname'] . "/pages/$nextpage.phtml\">&gt;&gt;&gt;</a>";
}

$tpl->set_var("PAGES", $pagestr);

$tpl->set_var("NUMTHREADS", $numthreads);
$tpl->set_var("NUMPAGES", $numpages);

$time = time();
$tpl->set_var("TIME", $time);

function display_thread($thread)
{
  global $user, $forum;

  if (!empty($thread['flags'])) {
    $options = explode(",", $thread['flags']);
    foreach ($options as $name => $value)
      $thread["flag.$value"] = true;
  }

  list($messages, $tree) = fetch_thread($thread);
  if (!isset($messages) || !count($messages))
    return array(0, "", "");

  $count = count($messages);

  if (isset($user->pref['Collapsed']))
    $messagestr = print_collapsed($thread, reset($messages), $count - 1);
  else
    $messagestr = list_thread(print_subject, $messages, $tree, reset($tree), $thread);

  if (empty($messagestr))
    return array(0, "", "");

  $message = reset($messages);
  $state = $message['state'];

  return array($count, "<ul class=\"thread\">\n" . $messagestr . "</ul>", $state);
}

$numshown = 0;

if ($curpage == 1 && $enable_global_messages) {
  /* PHP has a 32 bit limit even tho the type is a BIGINT, 64 bits */
  for ($i = 0; $i < 32; $i++) {
    $gmsg = sql_querya("select * from f_global_messages where gid = $i");
    if ($gmsg && strlen($gmsg['url'])>0) {
      if (!($user->gmsgfilter & (1 << $i)) && ($user->admin() || $gmsg['state'] == "Active")) {
	$tpl->set_var("CLASS", "grow" . ($numshown % 2));
	$gid = "gid=" . $gmsg['gid'];
	$gpage = "page=" . $script_name . $path_info;
	$gtoken = "token=" . $user->token();

	$messages = "<a href=\"" .
	    $gmsg['url'] . "\" target=\"_top\">" .
	    $gmsg['subject'] .  "</a>&nbsp;&nbsp;-&nbsp;&nbsp;<b>" .
	    $gmsg['name'] .  "</b>&nbsp;&nbsp;<font size=-2><i>" .
	    $gmsg['date'] .  "</i></font>";

	if ($user->admin()) {
	    if ($gmsg['state']=='Active') {
		$state='state=Inactive'; $state_title = "Delete";$state_txt = "da";
		$messages .= " (<font color=\"green\"><b>Active</b></font>)";
	    } elseif ($gmsg['state']=='Inactive'){
		$state='state=Active'; $state_title = "Undelete";$state_txt = "ug";
		$messages .= " (<font color=\"red\"><b>Deleted</b></font>)";
	    }
	    $messages .= " <a href=\"/gmessage.phtml?$gid&amp;$state&amp;$gpage&amp;$gtoken\" title=\"$state_title\">$state_txt</a>";
	    $messages .= " <a href=\"/admin/gmessage.phtml?$gid&amp;edit\" title=\"Edit message\" target=\"_blank\">edit</a>";
	}

	$messagelinks="<a href=\"/gmessage.phtml?$gid&amp;hide=1&amp;$gpage&amp;$gtoken\" class=\"up\" title=\"hide\">rm</a>";

	$tpl->set_var("MESSAGES", "<ul class=\"thread\"><li>$messages</ul>");
	$tpl->set_var("MESSAGELINKS", $messagelinks);
	$tpl->parse("_row", "row", true);
	$numshown++;
      }
    }
  }
}

$tthreadsshow = 0;

if (isset($tthreads)) {
  reset($tthreads);
  while (list(, $tthread) = each($tthreads)) {
    $index = find_thread_index($tthread['tid']);
    if (!isset($index))
      continue;

    /* Some people have duplicate threads tracked, they'll eventually fall */
    /*  off, but for now this is a simple workaround */
    if (isset($threadshown[$tthread['tid']]))
      continue;
   
    /* TZ: unixtime is seconds since epoch */ 
    $sql = "select *, UNIX_TIMESTAMP(tstamp) as unixtime from f_threads" . $indexes[$index]['iid'] . " where tid = '" . addslashes($tthread['tid']) . "'";
    $result = mysql_query($sql) or sql_error($sql);

    if (!mysql_num_rows($result))
      continue;

    $thread = mysql_fetch_array($result);
    if ($thread['unixtime'] > $tthread['unixtime']) {
      $threadshown[$thread['tid']] = 'true';

      if ($curpage != 1)
        continue;

      $tpl->set_var("CLASS", "trow" . ($numshown % 2));

      list($count, $messagestr) = display_thread($thread);

      if (!$count)
        continue;

      $numshown++;
      $tthreadsshown++;

      /* If the thread is tracked, we know they are a user already */
      $messagelinks = "<a href=\"/" . $forum['shortname'] . "/untrack.phtml?tid=" . $thread['tid'] . "&amp;page=" . $script_name . $path_info . "&amp;token=" . $user->token() . "\" class=\"ut\" title=\"Untrack thread\">ut</a>";
      if ($count > 1) {
        if (!isset($user->pref['Collapsed']))
          $messagelinks .= "<br>";
        else
          $messagelinks .= " ";

        $messagelinks .= "<a href=\"/" . $forum['shortname'] . "/markuptodate.phtml?tid=" . $thread['tid'] . "&amp;page=" . $script_name . $path_info . "&amp;token=" . $user->token() . "&amp;time=$time\" class=\"up\" title=\"Update thread\">up</a>";
      }

      $tpl->set_var("MESSAGES", $messagestr);
      $tpl->set_var("MESSAGELINKS", $messagelinks);

      $tpl->parse("_row", "row", true);
    }
  }
}

if (!$tthreadsshown)
  $tpl->set_var("update_all", "");

$skipthreads = ($curpage - 1) * $threadsperpage;

$threadtable = count($indexes) - 1;

while ($threadtable >= 0 && isset($indexes[$threadtable])) {
  if (threads($threadtable) > $skipthreads)
    break;

  $skipthreads -= threads($threadtable);
  $threadtable--;
}

if ($curpage != 1 && ($threadtable < 0 || !isset($indexes[$threadtable]))) {
  err_not_found("Page out of range");
  exit;
}

while ($numshown < $threadsperpage) {
  unset($result);

  while (isset($indexes[$threadtable])) {
    $index = $indexes[$threadtable];

    $ttable = "f_threads" . $index['iid'];
    $mtable = "f_messages" . $index['iid'];

    /* Get some more results */
    $sql = "select $ttable.tid, $ttable.mid, $ttable.flags, $mtable.state from $ttable, $mtable where" .
	" $ttable.tid >= " . $index['mintid'] . " and" .
	" $ttable.tid <= " . $index['maxtid'] . " and" .
	" $ttable.mid >= " . $index['minmid'] . " and" .
	" $ttable.mid <= " . $index['maxmid'] . " and" .
	" $ttable.mid = $mtable.mid and ( $mtable.state = 'Active' ";
    if ($user->capable($forum['fid'], 'Delete'))
      $sql .= "or $mtable.state = 'Deleted' or $mtable.state = 'Moderated' or $mtable.state = 'OffTopic' "; 
    else {
      if (isset($user->pref['ShowModerated']))
        $sql .= "or $mtable.state = 'Moderated' ";

      if (isset($user->pref['ShowOffTopic']))
        $sql .= "or $mtable.state = 'OffTopic' ";
    }

    if ($user->valid())
      $sql .= "or $mtable.aid = " . $user->aid;

    $sql .= " )";
    
    if ($user->aid != 996) 
      $sql .= " and $mtable.aid != 996";

    /* Sort all of the messages by date and descending order */
    $sql .= " order by $ttable.tid desc";

    /* Limit to the maximum number of threads per page */
    $sql .= " limit $skipthreads," . ($threadsperpage - $numshown);

    $result = mysql_query($sql) or sql_error($sql);

    if (mysql_num_rows($result))
      break;

    $threadtable--;
  }

  if (!isset($indexes[$threadtable]))
    break;

  $skipthreads += mysql_num_rows($result);

  while ($thread = mysql_fetch_array($result)) {
    if (isset($threadshown[$thread['tid']]))
      continue;

    list($count, $messagestr) = display_thread($thread);

    if (!$count)
      continue;

/*
    if ($thread['state'] == 'Deleted')
      $tpl->set_var("CLASS", "drow" . ($numshown % 2));
    else if ($thread['state'] == 'Moderated')
      $tpl->set_var("CLASS", "mrow" . ($numshown % 2));
    else
*/
      $tpl->set_var("CLASS", "row" . ($numshown % 2));

    $numshown++;

    if ($user->valid()) {
      if (isset($tthreads_by_tid[$thread['tid']]))
        $messagelinks = " <a href=\"/" . $forum['shortname'] . "/untrack.phtml?tid=" . $thread['tid'] . "&amp;page=" . $script_name . $path_info . "&amp;token=" . $user->token() . "\" class=\"ut\" title=\"Untrack thread\">ut</a>";
      else
        $messagelinks = " <a href=\"/" . $forum['shortname'] . "/track.phtml?tid=" . $thread['tid'] . "&amp;page=" . $script_name . $path_info . "&amp;token=" . $user->token() . "\" class=\"tt\" title=\"Track thread\">tt</a>";
    } else
      $messagelinks = "";

    $tpl->set_var("MESSAGES", $messagestr);
    $tpl->set_var("MESSAGELINKS", $messagelinks);

    $tpl->parse("_row", "row", true);
  }

  mysql_free_result($result);
}

if (!$numshown)
  $tpl->set_var($table_block, "<font size=\"+1\">No messages in this forum</font><br>");

/*
$active_users = sql_query1("select count(*) from f_visits where UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(tstamp) <= 15 * 60 and aid != 0");
$active_guests = sql_query1("select count(*) from f_visits where UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(tstamp) <= 15 * 60 and aid = 0");
*/

$tpl->set_var(array(
  "ACTIVE_USERS" => $active_users,
  "ACTIVE_GUESTS" => $active_guests,
));

unset($thread);

require_once("postform.inc");
render_postform($tpl, "post", $user);

print generate_page($forum['name'], $tpl->parse("content", "showforum"));
?>
