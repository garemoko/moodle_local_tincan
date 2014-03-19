<?php
$observers = array(
    array(
        'eventname'   => '\mod_quiz\event\attempt_started',
        'callback'    => '\local_tincan\tincan::tincan_quiz_attempt_started',
        'includefile' => 'local\tincan\lib.php',
        'internal' => false,
    ),
    array(
        'eventname'   => '\mod_quiz\event\attempt_submitted',
        'callback'    => '\local_tincan\tincan::tincan_quiz_attempt_submitted',
        'includefile' => 'local\tincan\lib.php',
        'internal' => false,
    ),
);