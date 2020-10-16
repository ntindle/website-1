<?php

if (php_sapi_name() != "cli") {
    die();
}

require_once(__DIR__ . '/../../../template/config.php');
require_once(__DIR__ . '/admin.php');

$message = $argv[1];
$prev = $argv[2];
$current = $argv[3];
$len = strlen(trim($message));

if ($len == 0) {
    die();
}

$did_match = preg_match_all("@^\[(.+?)\] \[(.+?)\] \[(.+?)\] \[(.+?)\] (.+?)$@ms", $message, $matches);

if ($did_match) {
    foreach ($matches[0] as $k => $m) {
        $timestamp = $matches[1][$k];
        $error_type = $matches[2][$k];
        $process_pid = $matches[3][$k];
        $request_info = $matches[4][$k];
        $error_message = $matches[5][$k];

        AdminBot::send_message(
            "```accesslog\n({$prev} => {$current})\n[{$timestamp}]\n[{$error_type}]\n[{$process_pid}]\n[{$request_info}]\n\n{$error_message}```", DISCORD_WEB_LOGS_CHANNEL_ID);
    }
} else {
    AdminBot::send_message("```({$prev} => {$current})\n[ERROR LOG MESSAGE PARSE FAILED]\n{$message}```", DISCORD_WEB_LOGS_CHANNEL_ID);
}
