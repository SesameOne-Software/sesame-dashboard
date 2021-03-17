<?
require("api/discord.php");
require("api/util.php");

//if(isset($_SESSION['access_token']) && strlen($_SESSION['access_token'])) {
   // header('Location: dashboard.php');
    //exit();
//}
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
            <div class="alert alert-success text-center" role="alert" data-aos="fade-up" data-aos-once="true" style="background-color: rgb(255,92,92);color: rgb(255,255,255);font-family: Segoe UI;font-size: 22px;padding: 12px;margin: 16px;box-shadow: 3px 3px 4px rgba(0,0,0,0.2);"><span style="font-size: 17px;font-family: Roboto, sans-serif;">Site is currently under construction. Please be patient.</span></div>
        </div>
    </div>
    <div class="article-clean" style="background-color: #20262d;">
        <div class="container">
            <div class="row" style="margin-left: 0px;margin-right: 0px;">
                <div class="col-lg-10 col-xl-8 offset-lg-1 offset-xl-2" style="padding-right: 15px;padding-left: 15px;max-width: 100%;margin-left: 0px;min-width: 100%;">
                    <div class="intro"></div>
                    <div class="text"></div>
                    <div class="jumbotron" data-aos="fade-up" data-aos-once="true" style="align-content: center;margin: auto;width: 300px;margin-bottom: 10%;margin-top: 10%;">
                        <p></p><img src="assets/img/discord_logo.svg" style="width: 120px;height: 120px;margin: auto;display: block;">
                        <h1 style="font-family: Roboto, sans-serif;font-weight: 100;padding-bottom: 12px;text-align: center;">Sign in With Discord</h1><a href="<? echo url("799545959175815188", "https://sesame.one/dashboard.php", "identify email connections guilds"); ?>" class="btn btn-primary" type="button" style="background-color: #7289da;">Sign In</a></div>
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