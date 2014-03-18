<?php
$handlers = array (
'quiz_attempt_started' => array (
        'handlerfile'      => '/local/tincan/lib.php',
        'handlerfunction'  => array('tincan_quiz_attempt_started'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),
    'quiz_attempt_submitted' => array (
        'handlerfile'      => '/local/tincan/lib.php',
        'handlerfunction'  => array('tincan_quiz_attempt_submitted'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),
);

$observers = array(
    array(
        'eventname'   => '\mod_quiz\event\attempt_started',
        'callback'    => '\local_tincan\tincan::tincan_quiz_attempt_started',
        'includefile' => 'local\tincan\lib.php',
        'internal' => false,
    ),
);