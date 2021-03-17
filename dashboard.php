<?
require("api/discord.php");
require("api/util.php");

//include_once "header.php";

/* discord auth */
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    unset($_SESSION['access_token']);
    header("Location: auth.php" );
    exit();
}

if (!isset($_SESSION['access_token'])){
    init("https://sesame.one/dashboard.php", "799545959175815188", "ORzTCp1Y2m8jzeWSbPt0p-9hEQ-oTUAl", $bot_token="Nzk5NTQ1OTU5MTc1ODE1MTg4.YAFJMw.uXZLmcKY3F8LGXf2zObqZ-RrTp4");
}

get_user();
$_SESSION['guilds'] = get_guilds();

if (!check_state($_GET['state'])) {
    unset($_SESSION['access_token']);
    header("Location: auth.php" );
    exit();
}

/* check if user is whitelisted and already in database; if not, then request them to enter invitation code */
$config = require("api/config.php");

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO("mysql:host=" . $config["hostname"] . ";dbname=" . $config["dbname"] . ";charset=utf8mb4", $config["dbuser"], $config["dbpassword"], $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE discord_id=?");
$stmt->execute([$_SESSION['user_id']]);
$data = $stmt->fetch();

/* if they attempt to register with invite code, lets check it before loading anything else */
if (!$data && isset($_GET['invite_code'])) {
    if ( !preg_match("/^(?:[0-9A-Za-z]{4}-?){4}$/", $_GET['invite_code']) ) {
        $registration_error = "Invitation code is formatted incorrectly.";
        $data = null;
    }
    else {
        $stmt = $pdo->prepare("SELECT * FROM invitations");
        $stmt->execute();
        $data = $stmt->fetchAll();

        $my_invite_common_format = str_replace("-", "", $_GET['invite_code']);

        foreach($data as $entry) {
            if (!strlen($entry["used_by"])) {
                $invite_common_format = $entry["invite_code"];
                $invite_common_format = str_replace("-", "", $invite_common_format);

                /* we have a valid invite code */
                if (!strcasecmp($my_invite_common_format, $invite_common_format)) {
                    /* set invite code as used */
                    $stmt = $pdo->prepare("UPDATE invitations SET used_by=? WHERE invite_code=?");
                    $stmt->execute([$_SESSION['user_id'], $entry["invite_code"]]);

                    /* add / whitelist user */
                    $stmt = $pdo->prepare("INSERT INTO users (discord_id, discord_username, email, invitee_id, registration_date, ip, last_login, permissions, hwid, subscription_time, invites_left) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'], $entry["created_by"], time(), client_ip(), time(), 0, "", time(), 0]);

                    $stmt = $pdo->prepare("SELECT * FROM users WHERE discord_id=?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $data = $stmt->fetch();

                    $registration_error = "";

                    header("Location: dashboard.php?code=" . $_GET['code'] . "&state=" . $_GET['state']);
                    exit();
                    break;
                }
            }
        }

        $registration_error = "Invitation code is invalid.";
        $data = null;
    }
}

function generateRandomString($length = 10) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/* if user exists, show them the dashboard */
if ($data) {
    $stmt1 = $pdo->prepare("UPDATE users SET discord_username=?, email=?, ip=?, last_login=? WHERE discord_id=?");
    $stmt1->execute([$_SESSION["username"], $_SESSION["email"], client_ip(), time(), $_SESSION['user_id']]);

    if(isset($_GET["create_invite"]) && $_GET["create_invite"]) {
        /* check if user can create invites */
        if ( $data["permissions"] >= 5 || $data["invites_left"] > 0) {
            if ($data["invites_left"] > 0 && $data["permissions"] < 5) {
                $stmt2 = $pdo->prepare("UPDATE users SET invites_left=? WHERE discord_id=? limit 1");
                $stmt2->execute([$data["invites_left"] - 1, $_SESSION['user_id']]);
            }

            $stmt2 = $pdo->prepare("INSERT INTO invitations (invite_code, created_by, used_by) VALUES (?, ?, ?)");
            $stmt2->execute([generateRandomString(4) . "-" . generateRandomString(4) . "-" . generateRandomString(4) . "-" . generateRandomString(4), $_SESSION['user_id'], ""]);
        }

        header("Location: dashboard.php?code=" . $_GET['code'] . "&state=" . $_GET['state']);
        exit();
    }

    if (isset($_GET["action"])) {
        if ($data["permissions"] >= 4) {
            $target_user = empty($_GET['uid']) ? -1 : $_GET['uid'];

            if ($target_user == -1 && !empty($_GET['discord_id'])) {
                $stmt1 = $pdo->prepare("SELECT * FROM users WHERE discord_id=? limit 1");
                $stmt1->execute([$_GET['discord_id']]);
                $target_data = $stmt1->fetch();

                if ($target_data)
                    $target_user = $target_data["id"];
            }

            if ($target_user == -1 && !empty($_GET['ip_address'])) {
                $stmt1 = $pdo->prepare("SELECT * FROM users WHERE ip=? limit 1");
                $stmt1->execute([$_GET['ip_address']]);
                $target_data = $stmt1->fetch();

                if ($target_data)
                    $target_user = $target_data["id"];
            }

            switch ($_GET["action"]) {
            case "reset_hwid": {
                if ($target_user != -1) {
                    $stmt1 = $pdo->prepare("UPDATE users SET hwid=? WHERE id=? limit 1");
                    $stmt1->execute(["", $target_user]);
                }
            } break;
            case "change_permissions": {
                if ($target_user != -1 && isset($_GET['permissions']) && !empty($_GET['permissions']) && $_GET['permissions'] != -1 && /*cant set permissions higher than or = to our own*/ (($data['permissions'] == 6) ? true : ($data['permissions'] > $_GET['permissions']) ) ) {
                    $stmt1 = $pdo->prepare("UPDATE users SET permissions=? WHERE id=? limit 1");
                    $stmt1->execute([$_GET['permissions'], $target_user]);
                }
            } break;
            case "grant_invitations": {
                if ($target_user != -1 && isset($_GET['invites_amount']) && !empty($_GET['invites_amount']) && $_GET['invites_amount'] != 0) {
                    $stmt1 = $pdo->prepare("UPDATE users SET invites_left=invites_left+? WHERE id=? limit 1");
                    $stmt1->execute([$_GET['invites_amount'], $target_user]);
                }
            } break;
            case "extend_subscription": {
                if ($target_user != -1 && isset($_GET['subscription_amount']) && !empty($_GET['subscription_amount']) && $_GET['subscription_amount'] != 0) {
                    $stmt1 = $pdo->prepare("SELECT * FROM users WHERE id=? limit 1");
                    $stmt1->execute([$target_user]);
                    $subscription_data = $stmt1->fetch();

                    if ($subscription_data) {
                        $new_subscription_time = 0; 
                        /* already have active subscription, add onto the time */
                        if ($subscription_data["subscription_time"] > time()) {
                            $new_subscription_time = $subscription_data["subscription_time"] + $_GET['subscription_amount'] * 86400 /* 1 day in seonds */;
                        }
                        /* need new subscription */
                        else {
                            $new_subscription_time = time() + $_GET['subscription_amount'] * 86400 /* 1 day in seonds */;
                        }

                        $stmt1 = $pdo->prepare("UPDATE users SET subscription_time=? WHERE id=? limit 1");
                        $stmt1->execute([$new_subscription_time, $target_user]);
                    }
                }
            } break;
            case "ban_user": {
                if ($target_user != -1 && isset($_GET['ban_reason']) && !empty($_GET['ban_reason'])) {
                    $stmt1 = $pdo->prepare("UPDATE users SET permissions=-1, ban_reason=? WHERE id=? limit 1");
                    $stmt1->execute([$_GET['ban_reason'], $target_user]);
                }
            } break;
            case "lift_ban": {
                if ($target_user != -1) {
                    $stmt1 = $pdo->prepare("UPDATE users SET permissions=0, ban_reason=? WHERE id=? limit 1");
                    $stmt1->execute(["", $target_user]);
                }
            } break;
            case "upload": {
                if ($data["permissions"] >= 5) {

                }
            } break;
            default: {
            } break;
        }
        }

        header("Location: dashboard.php?code=" . $_GET['code'] . "&state=" . $_GET['state']);
        exit();
    }

    /* display custom message and stop user from loading page if account is disabled */
    if($data["permissions"] == -1) {
?>

<!DOCTYPE html>
<html>

<head>
<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>sesame</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Bitter:400,700">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lora">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/fonts/fontawesome5-overrides.min.css">
    <link rel="stylesheet" href="assets/css/Article-Clean.css">
    <link rel="stylesheet" href="assets/css/Features-Clean.css">
    <link rel="stylesheet" href="assets/css/Footer-Basic.css">
    <link rel="stylesheet" href="assets/css/Header-Dark.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.1.1/aos.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div>
        <div class="header-dark" style="opacity: 1;background-color: #20262d;padding-bottom: 1px;">
            <nav class="navbar navbar-dark navbar-expand-lg navigation-clean-search">
                <div class="container"><a href="#"><img style="width: 3em;height: 2em;" src="assets/img/logo.png"></a></div>
            </nav>
        </div>
    </div>
    <div class="article-clean" style="background-color: #20262d;">
        <div class="container">
            <div class="row" style="margin-left: 0px;margin-right: 0px;">
                <div class="col-lg-10 col-xl-8 offset-lg-1 offset-xl-2" style="padding-right: 15px;padding-left: 15px;max-width: 100%;margin-left: 0px;min-width: 100%;">
                    <div class="intro"></div>
                    <?if (isset($registration_error) && strlen($registration_error)) {?>
                        <div class="alert alert-success text-center" role="alert" data-aos="fade-up" data-aos-once="true"><span style="font-size: 17px;"><?echo $registration_error;?></span></div>
                    <?}?>
                    <div class="text"></div>
                    <div class="jumbotron" data-aos="fade-up" data-aos-once="true" style="align-content: center;margin: auto;width: 300px;margin-bottom: 10%;margin-top: 10%;">
                        <p></p>
                        <h1 style="font-family: Roboto, sans-serif;font-weight: 100;padding-bottom: 12px;text-align: center;">Your Account Has Been Disabled</h1>
                        <p style="padding-top: 8px;font-size: 14px;">Reason: <?echo $data["ban_reason"];?></p>
                        <a class="btn btn-primary" type="submit" style="margin-top: 8px;" href="index.php">Close</a>
                        <p style="padding-top: 8px;font-size: 11px;">Your account has been disabled by an administrator because of a violation of our <a href="terms.php">Terms of Service</a> and/or <a href="privacy.php">Privacy Policy</a> associated with your account. If you believe your ban is unjustified, create a support ticket and provide information about the situation.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-basic" style="background-color: #20262d;">
        <footer>
            <div class="social"><a href="https://www.youtube.com/channel/UCDOJMu3_ftxJDtPWq6ceigQ" style="font-family: Segoe UI;"><i class="fab fa-youtube" style="color: rgb(255,0,0);filter: blur(0px);"></i></a><a href="https://discord.gg/RaUvA5JRxZ" style="font-family: Segoe UI;"><i class="fab fa-discord" style="color: #7289DA;"></i></a></div>
            <ul
                class="list-inline">
                <li class="list-inline-item"><a href="index.php">Showcase</a></li>
                <li class="list-inline-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="list-inline-item"><a href="terms.php">Terms</a></li>
                <li class="list-inline-item"><a href="privacy.php">Privacy Policy</a></li>
                </ul>
                <p class="copyright" style="font-family: Roboto, sans-serif;">Sesame Software © 2019 - 2021</p>
        </footer>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/bs-init.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.1.1/aos.js"></script>
</body>

</html>

<?
        exit();
}
?>

<!DOCTYPE html>
<html>

<head>
<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>sesame</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Bitter:400,700">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lora">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/fonts/fontawesome5-overrides.min.css">
    <link rel="stylesheet" href="assets/css/Article-Clean.css">
    <link rel="stylesheet" href="assets/css/Features-Clean.css">
    <link rel="stylesheet" href="assets/css/Footer-Basic.css">
    <link rel="stylesheet" href="assets/css/Header-Dark.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.1.1/aos.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div>
        <div class="header-dark" style="opacity: 1;background-color: #20262d;padding-bottom: 1px;">
            <nav class="navbar navbar-dark navbar-expand-lg navigation-clean-search">
                <div class="container"><a href="#"><img style="width: 3em;height: 2em;" src="assets/img/logo.png"></a></div>
            </nav>
        </div>
    </div>

    <div class="modal fade" role="dialog" tabindex="-1" id="users-modal">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    

<form action="dashboard.php" method="get"><button class="btn btn-primary" type="submit" style="margin-top: 8px;">Search
    <?
    if ($data["permissions"] < 5)
        echo " - " . $data["invites_left"] . " Remaining";
?></button><input class="form-control"
                                type="hidden" name="code" value="<?echo $_GET['code'];?>"><input class="form-control" type="hidden" name="state" value="<?echo $_GET['state'];?>">
                                <input class="form-control" type="hidden" name="create_invite" value="1"></form>
                    <button class="btn btn-light" type="button" data-dismiss="modal" style="color: #fff; margin-top: 8px;">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" role="dialog" tabindex="-1" id="invites-modal">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr style="border: none;">
                                    <th style="border: none;">Invitation Code</th>
                                    <th style="border: none;">User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?
                                $stmt1 = $pdo->prepare("SELECT * FROM invitations WHERE created_by=?");
                                $stmt1->execute([$_SESSION['user_id']]);
                                $data1 = $stmt1->fetchAll();

                                if ($data1){
                                    foreach($data1 as $entry) {
                                        echo "<tr>\n";
                                        echo "<td>" . $entry["invite_code"] . "</td>\n";
                                        if (isset($entry["used_by"]) && strlen($entry["used_by"])) {
                                            $stmt2 = $pdo->prepare("SELECT * FROM users WHERE discord_id=? limit 1");
                                            $stmt2->execute([$entry["used_by"]]);
                                            $data2 = $stmt2->fetch();

                                            if ($data2) {
                                                echo "<td>" . $data2["discord_username"] . "</td>\n";
                                            }
                                            else {
                                                echo "<td></td>\n";
                                            }
                                        }
                                        else {
                                            echo "<td></td>\n";
                                        }
                                        echo "</tr>\n";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

<form action="dashboard.php" method="get"><button class="btn btn-primary" type="submit" style="margin-top: 8px;">Create Invitation
    <?
    if ($data["permissions"] < 5)
        echo " - " . $data["invites_left"] . " Remaining";
?></button><input class="form-control"
                                type="hidden" name="code" value="<?echo $_GET['code'];?>"><input class="form-control" type="hidden" name="state" value="<?echo $_GET['state'];?>">
                                <input class="form-control" type="hidden" name="create_invite" value="1"></form>
                    <button class="btn btn-light" type="button" data-dismiss="modal" style="color: #fff; margin-top: 8px;">Close</button></div>
            </div>
        </div>
    </div>



    <div class="article-clean" style="background-color: #20262d;">
        <div class="container">
            <div class="row" style="margin-left: 0px;margin-right: 0px;">
                <div class="col-lg-10 col-xl-8 offset-lg-1 offset-xl-2" style="padding-right: 15px;padding-left: 15px;max-width: 100%;margin-left: 0px;min-width: 100%;">
                    <div class="intro"></div>
                    <div class="text"></div>
                    <div class="jumbotron" data-aos="fade-up" data-aos-once="true">
                        <p></p>
                        <h1 style="font-family: Roboto, sans-serif;font-weight: 100;padding-bottom: 12px;">My Profile</h1>
                        <h1 style="font-size: 22px;text-align: center;"><?echo $_SESSION["username"];?></h1>
                        <img src="https://cdn.discordapp.com/avatars/<? echo $_SESSION['user_id'] . "/" . $_SESSION['user_avatar'] . '.png'; ?>" style="display: block; margin: auto;"/>
                        <p style="font-size: 1rem;margin-bottom: 6px;">UID: <?echo $data["id"];?></p>
                        <p style="font-size: 1rem;margin-bottom: 6px;">Join date: <?
                        $date = date_create();
                        date_timestamp_set($date, $data["registration_date"]);
                        echo date_format($date, 'Y-m-d');
                        ?></p>
                        <p style="font-size: 1rem;margin-bottom: 6px;">Invited by: <?
                        $stmt1 = $pdo->prepare("SELECT * FROM users WHERE discord_id=? limit 1");
                        $stmt1->execute([$data["invitee_id"]]);
                        $data1 = $stmt1->fetch();
                        echo $data1["discord_username"];
                        ?></p>
                        <p style="font-size: 1rem;margin-bottom: 6px;">Permissions: <?
                        switch ($data["permissions"]) {
                            case -1: echo "Banned"; break;
                            case 0: echo "User"; break;
                            case 1: echo "Subscribed"; break;
                            case 2: echo "Beta"; break;
                            case 3: echo "Support"; break;
                            case 4: echo "Moderator"; break;
                            case 5: echo "Developer"; break;
                            case 6: echo "Administrator"; break;
                            default: echo "Unknown"; break;
                        }
                        ?></p>
                        <p style="font-size: 1rem;margin-bottom: 6px;">Remaining invitation codes: <?
if ($data["permissions"] >= 5)
    echo "Does not apply";
else
    echo $data["invites_left"];
                        ?></p>
                        <p style="font-size: 1rem;margin-bottom: 6px;">Subscription days remaining: <?
                        if ($data["permissions"] >= 3) {
                            echo "Does not expire";
                        }
                        else {
                            echo floor(max(0, $data["subscription_time"] - time()) / 86400.0);
                        }
                        ?></p>
                        <p style="font-size: 1rem;margin-bottom: 6px;">HWID: <?echo (($data["hwid"] == null) ? "Not set" : "Set");?></p>
                        <?if ($data["hwid"] != null){?>
                            <button class="btn btn-primary" type="button">Reset HWID</button>
                        <?}?>
                        <button class="btn btn-primary" type="button" style="margin-bottom: 8px;">Extend Subscription</button><button class="btn btn-primary" type="button" style="margin-bottom: 8px;" data-toggle="modal" data-target="#invites-modal">View Invitation Codes</button>
                        <?if ($data["permissions"] >= 1){?>
                        <a class="btn btn-primary" type="button" href="https://sesame.one/PH/ph_download.php" target="_blank" style="margin-bottom: 8px;">Download Custom Loader</a>
<?}?>
<button class="btn btn-primary" type="button" style="margin-bottom: 8px;" data-toggle="modal" data-target="#users-modal">User Search</button>
                    </div>
                </div>
                <div class="col-lg-10 col-xl-8 offset-lg-1 offset-xl-2" style="padding-right: 15px;padding-left: 15px;max-width: 100%;margin-left: 0px;min-width: 100%;">
                    <div class="intro"></div>
                    <div class="text"></div>
                    <div class="jumbotron" data-aos="fade-up" data-aos-once="true">
                        <p></p>
                        <h1 style="font-family: Roboto, sans-serif;font-weight: 100;padding-bottom: 12px;">Support</h1>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr style="border: none;">
                                        <th style="border: none;width: 177px;">Date</th>
                                        <th style="border: none;">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!--
                                    <tr>
                                        <td>1/1/2021</td>
                                        <td>{Description}</td>
                                    </tr>
                                    <tr>
                                        <td>1/1/2021<br></td>
                                        <td>{Description}<br></td>
                                    </tr>
                                -->
                                </tbody>
                            </table>
                        </div><button class="btn btn-primary" type="button" style="margin-bottom: 8px;">Report Bug</button><button class="btn btn-primary" type="button" style="margin-bottom: 8px;">Report Sesame TOS Violation<br></button><button class="btn btn-primary"
                            type="button">Report Other Issue</button></div>
                </div>

                <?if ($data["permissions"] >= 4) {?>
                    <div class="col-lg-10 col-xl-8 offset-lg-1 offset-xl-2" style="padding-right: 15px;padding-left: 15px;max-width: 100%;margin-left: 0px;min-width: 100%;">
                    <div class="intro"></div>
                    <div class="text"></div>
                    <div class="jumbotron" data-aos="fade-up" data-aos-once="true">
                        <p></p>
                        <form action="dashboard.php" method="get">
                        <h1 style="font-family: Roboto, sans-serif;font-weight: 100;padding-bottom: 12px;">Admin Panel</h1><input type="search" name="uid" placeholder="UID" style="min-width: 31%;max-width: 31%;margin-right: 3.5%;"><input type="search" name="discord_id" placeholder="Discord ID" style="min-width: 31%;max-width: 31%;margin-right: 3%;">
                        <input
                            type="search" name="ip_address" placeholder="IP Address" style="min-width: 31%;max-width: 31%;margin-bottom: 32px;"><select style="max-width: 100%;min-width: 100%;" name="permissions"><option value="-1" selected="">No Changes</option>
                            <?if ($data["permissions"] > 0){?><option value="0">User</option><?}?>
                            <?if ($data["permissions"] > 1){?><option value="1">Subscribed</option><?}?>
                            <?if ($data["permissions"] > 2){?> <option value="2">Beta</option><?}?>
                            <?if ($data["permissions"] >= 6){?><option value="3">Support</option><?}?>
                            <?if ($data["permissions"] >= 6){?><option value="4">Moderator</option><?}?>
                            <?if ($data["permissions"] >= 6){?><option value="5">Developer</option><?}?>
                            <?if ($data["permissions"] >= 6){?><option value="6">Administrator</option><?}?>
                            </select>
                            <button
                                class="btn btn-primary" type="submit" name="action" value="change_permissions" style="margin-bottom: 32px;">Change Permissions<br></button><input type="number" style="min-width: 100%;max-width: 100%;" min="0" max="10" step="1" placeholder="Amount of Invitations" name="invites_amount"><button class="btn btn-primary" type="submit" name="action" value="grant_invitations"
                                    style="margin-bottom: 32px;">Grant Invitations</button>
                                    <input type="number" style="min-width: 100%;max-width: 100%;" min="0" max="30" step="1" placeholder="Days to Extend Subscription" name="subscription_amount"><button class="btn btn-primary" type="submit" name="action" value="extend_subscription"
                                    style="margin-bottom: 32px;">Extend Subscription</button><input type="text" style="min-width: 100%;max-width: 100%;" name="ban_reason" placeholder="Ban reason"><button class="btn btn-primary" type="submit" name="action" value="ban_user" style="margin-bottom: 8px;">Ban User</button>
                                <button
                                    class="btn btn-primary" type="submit" name="action" value="lift_ban" style="margin-bottom: 32px;">Lift Ban</button>
                                    
                                    <input class="form-control"
                                type="hidden" name="code" value="<?echo $_GET['code'];?>"><input class="form-control" type="hidden" name="state" value="<?echo $_GET['state'];?>">

                                </form>

                    </div>
                </div>
                <?}?>
            </div>
        </div>
    </div>
    <div class="footer-basic" style="background-color: #20262d;">
        <footer>
            <div class="social"><a href="https://www.youtube.com/channel/UCDOJMu3_ftxJDtPWq6ceigQ" style="font-family: Segoe UI;"><i class="fab fa-youtube" style="color: rgb(255,0,0);filter: blur(0px);"></i></a><a href="https://discord.gg/RaUvA5JRxZ" style="font-family: Segoe UI;"><i class="fab fa-discord" style="color: #7289DA;"></i></a></div>
            <ul
                class="list-inline">
                <li class="list-inline-item"><a href="index.php">Showcase</a></li>
                <li class="list-inline-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="list-inline-item"><a href="terms.php">Terms</a></li>
                <li class="list-inline-item"><a href="privacy.php">Privacy Policy</a></li>
                </ul>
                <p class="copyright" style="font-family: Roboto, sans-serif;">Sesame Software © 2019 - 2021</p>
        </footer>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/bs-init.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.1.1/aos.js"></script>
</body>

</html>

<?
}
/* show them invitation code prompt if they arent already registered */
else {
?>
<!DOCTYPE html>
<html>

<head>
<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>sesame</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Bitter:400,700">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lora">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/fonts/fontawesome5-overrides.min.css">
    <link rel="stylesheet" href="assets/css/Article-Clean.css">
    <link rel="stylesheet" href="assets/css/Features-Clean.css">
    <link rel="stylesheet" href="assets/css/Footer-Basic.css">
    <link rel="stylesheet" href="assets/css/Header-Dark.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.1.1/aos.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div>
        <div class="header-dark" style="opacity: 1;background-color: #20262d;padding-bottom: 1px;">
            <nav class="navbar navbar-dark navbar-expand-lg navigation-clean-search">
                <div class="container"><a href="#"><img style="width: 3em;height: 2em;" src="assets/img/logo.png"></a></div>
            </nav>
        </div>
    </div>
    <div class="article-clean" style="background-color: #20262d;">
        <div class="container">
            <div class="row" style="margin-left: 0px;margin-right: 0px;">
                <div class="col-lg-10 col-xl-8 offset-lg-1 offset-xl-2" style="padding-right: 15px;padding-left: 15px;max-width: 100%;margin-left: 0px;min-width: 100%;">
                    <div class="intro"></div>
                    <?if (isset($registration_error) && strlen($registration_error)) {?>
                        <div class="alert alert-success text-center" role="alert" data-aos="fade-up" data-aos-once="true"><span style="font-size: 17px;"><?echo $registration_error;?></span></div>
                    <?}?>
                    <div class="text"></div>
                    <div class="jumbotron" data-aos="fade-up" data-aos-once="true" style="align-content: center;margin: auto;width: 300px;margin-bottom: 10%;margin-top: 10%;">
                        <p></p>
                        <h1 style="font-family: Roboto, sans-serif;font-weight: 100;padding-bottom: 12px;text-align: center;">Invitation Code Required</h1>
                        <form action="dashboard.php" method="get"><input id="invite_code", name="invite_code" class="form-control" type="text" autocomplete="off" required><button class="btn btn-primary" type="submit" style="margin-top: 8px;">Confirm</button><input class="form-control"
                                type="hidden" name="code" value="<?echo $_GET['code'];?>"><input class="form-control" type="hidden" name="state" value="<?echo $_GET['state'];?>"></form>
                        <p style="padding-top: 8px;font-size: 11px;">By clicking Sign Up, you make it clear that you have read, and agree with our&nbsp;<a href="terms.php">Terms of Service</a>&nbsp;and <a href="privacy.php">Privacy Policy</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-basic" style="background-color: #20262d;">
        <footer>
            <div class="social"><a href="https://www.youtube.com/channel/UCDOJMu3_ftxJDtPWq6ceigQ" style="font-family: Segoe UI;"><i class="fab fa-youtube" style="color: rgb(255,0,0);filter: blur(0px);"></i></a><a href="https://discord.gg/RaUvA5JRxZ" style="font-family: Segoe UI;"><i class="fab fa-discord" style="color: #7289DA;"></i></a></div>
            <ul
                class="list-inline">
                <li class="list-inline-item"><a href="index.php">Showcase</a></li>
                <li class="list-inline-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="list-inline-item"><a href="terms.php">Terms</a></li>
                <li class="list-inline-item"><a href="privacy.php">Privacy Policy</a></li>
                </ul>
                <p class="copyright" style="font-family: Roboto, sans-serif;">Sesame Software © 2019 - 2021</p>
        </footer>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/bs-init.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.1.1/aos.js"></script>
</body>

</html>
<?
}
?>