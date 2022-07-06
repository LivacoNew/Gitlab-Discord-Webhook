<?php 
require_once __DIR__ . '/vendor/autoload.php';
use Livaco\EasyDiscordWebhook\DiscordWebhook;

//
// "config"
//

// the hooks that are here. the get parameter "hook" controls which url to use
$hooks = [
    'hook_1' => [
        'webhook' => "link_to_yer_webhook_bruh",
        "secret" => "super_secret_token"
    ],
    'hook_2' => [
        'webhook' => "poop",
        "secret" => "judy_is_hot"
    ],
];

// prefix for private commits
$privatePrefix = "/p";

//
// ded
//



function relativeTime($time) { // Thanks https://stackoverflow.com/a/7487809/12071005
    $d[0] = array(1,"second");
    $d[1] = array(60,"minute");
    $d[2] = array(3600,"hour");
    $d[3] = array(86400,"day");
    $d[4] = array(604800,"week");
    $d[5] = array(2592000,"month");
    $d[6] = array(31104000,"year");
    $w = array();
    $return = "";
    $now = time();
    $diff = ($now-$time);
    $secondsLeft = $diff;
    for($i=6;$i>-1;$i--) {
        $w[$i] = intval($secondsLeft/$d[$i][0]);
        $secondsLeft -= ($w[$i]*$d[$i][0]);
        if($w[$i]!=0) {
            $return.=" "; 
            $return.= abs($w[$i]) . " " . $d[$i][1];
            if(abs($w[$i]) > 1) {
                $return.="s";
            }
        }
    }
    return $return;
}


// get our hook
if(!isset($_GET['hook'])) {
    header('X-PHP-Response-Code: 503', true, 503);
    die("Bad Hook");
}
if(!array_key_exists($_GET['hook'], $hooks)) {
    header('X-PHP-Response-Code: 503', true, 503);
    die("Bad Hook");
}

$hook = $hooks[$_GET['hook']];

// confirm the secret
if(!isset($_SERVER['HTTP_X_GITLAB_TOKEN'])) {
    header('X-PHP-Response-Code: 503', true, 503);
    die("Forbidden");
}
$receivedSecret = $_SERVER['HTTP_X_GITLAB_TOKEN'];
if($receivedSecret != $hook['secret']) {
    header('X-PHP-Response-Code: 503', true, 503);
    die("Forbidden");
}

// get our data
$json = file_get_contents('php://input');
$data = json_decode($json, true);


// send the hook depending on the type 
$webhook = new DiscordWebhook($hook['webhook']);
$project = $data['project'];

switch($data['object_kind']) {
    case "push":
        $commits = $data['commits'];

        $webhook->setAuthor($data['user_name'], "", $data['user_avatar']);
        $webhook->setTitle(count($commits) . " commits in '" . $project['default_branch'] . "'");
        foreach($commits as $commit) {
            $timestamp = date("dS M Y", strtotime($commit['timestamp']));
            $added = count($commit['added']);
            $modified = count($commit['modified']);
            $removed = count($commit['removed']);
            if(substr($commit['message'], 0, 2) == $privatePrefix) {
                $webhook->addField("{$commit['author']['name']} on the $timestamp", "This commit is private.", false);
            } else {
                $webhook->addField("{$commit['author']['name']} on the $timestamp", "{$commit['message']}**+{$added}** Files **+-{$modified}** Files **-{$removed}** Files\n", false);
            }
        }
        $webhook->setColor("B024FF");
        $webhook->setFooter($project['name'], $project['avatar_url']);
        $webhook->setTimestamp(date("c"));
        $webhook->sendWebhook();

        break;

    case "pipeline":
        $pipeline = $data['object_attributes'];
        $commit = $data['commit'];
        $user = $data['user'];

        if($pipeline["finished_at"] == "") {
            die();
        }

        if($pipeline["status"] == "success") {
            // success
            $webhook->setTitle("Deployment Succeeded");
            $webhook->setDescription("Pipeline successfully finished running in " . relativeTime($pipeline['duration'] + time()) . ".");
            $webhook->setColor("50AF58");
        } else {
            // failure
            $webhook->setTitle("Deployment Failed");
            $webhook->setDescription("Pipeline failed to finish after " . relativeTime($pipeline['duration'] + time()) . ".");
            $webhook->setColor("E44141");
        }

        // No real need to display this
        // if(substr($commit['message'], 0, 2) == $privatePrefix) {
        //     $webhook->addField("Commit by " . $commit['author']['name'] . ".", "*This commit has been marked as private.*", false);
        // } else {
        //     $webhook->addField("Commit by " . $commit['author']['name'] . ".", $commit['message'], false);
        // }

        $webhook->setAuthor($user['name'], "", $user['avatar_url']);
        $webhook->setFooter($project['name'], $project['avatar_url']);
        $webhook->setTimestamp(date("c"));
        $webhook->sendWebhook();
        break;

    default: 
        header('X-PHP-Response-Code: 503', true, 503);
        die("Bad data.");
        break;
}