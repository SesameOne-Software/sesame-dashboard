<?
if ($_SERVER["REQUEST_METHOD"] != "POST" || $_SERVER["HTTP_USER_AGENT"] != "5club-loader-client") {
    echo "error";
    exit();
}

$config = require("config.php");

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

$stmt = $pdo->prepare("SELECT * FROM " . $config["dbtable"] .  " WHERE ip=?");
$stmt->execute([$_SERVER["REMOTE_ADDR"]]);
$data = $stmt->fetch();

$loader_info["modules"] = ["csgo", "csgo beta", "developer"];
$loader_info["modules"]
?>