<?php

date_default_timezone_set(@date_default_timezone_get());
$time = gettimeofday();
print "<b>Current Date/Time is: " . strftime("%m/%d/%Y %T", $time['sec']) . ' ' . intval(round($time['usec'] / 1000)) . 'ms</b><br><br>';
print 'If the time remains the same after refreshing the page, then you\'re viewing a Pagecached copy of this page';