<?php
if (!isset($forum)) {
  echo "Invalid forum\n";
  exit;
}

if (!$user->valid()) {
  header("Location: $page");
  exit;
}

$index = find_thread_index($tid);
if (!isset($index)) {
  echo "Invalid thread!\n";
  exit;
}

if ($_REQUEST['token'] != $user->token())
  err_not_found("Invalid token"); 

if (!isset($tthreads_by_tid[$tid])) {
  $sql = "insert into f_tracking ( fid, tid, aid, options ) values ( " . $forum['fid'] . ", '" . addslashes($tid) . "', '" . $user->aid . "', '' )";
  mysql_query($sql) or sql_error($sql);
}

Header("Location: $page");
?>
