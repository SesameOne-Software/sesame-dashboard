<?
require("api/discord.php");
require("api/util.php");
?>

<img src="https://cdn.discordapp.com/avatars/<? echo $_SESSION['user_id'] . "/" . $_SESSION['user_avatar'] . '.png'; ?>" class = "image-cropper"/>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
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

<style>
.username{
    color: transparent;
    font-family: Roboto, sans-serif;
    
    font-weight:bold;
    position: absolute; left:92%; top: 2%;
    background-image: url(https://media3.giphy.com/media/vLb3fGlWDnBMk/giphy-downsized.gif);
    -webkit-background-clip: text;
    background-clip: text;
}
 .image-cropper {
        width: 60px;
        height: 60px;
        position: absolute; left: 88.5%; top: 1%;
        overflow: hidden;
        border-radius: 50%;
    }
</style>

<h3 class = "username"><?echo $_SESSION['username'];?></h3>


<head>
    <title>Sesame</title>
<html lang="en-US">
<link rel="icon"
      type="download/png"
      href="https://sesame.one/assets/img/logo.png">
</head>
</html>