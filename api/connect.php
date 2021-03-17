<?
if ($_SERVER["REQUEST_METHOD"] != "POST" || $_SERVER["HTTP_USER_AGENT"] != "5club-loader-client") {
    echo "error";
    exit();
}

$config = require("config.php");
require_once("util.php");

$raw_content = file_get_contents("php://input");

if (!isset($raw_content) || !is_string($raw_content) || !strlen($raw_content)) {
    echo "error";
    exit();
}

$decoded = base64_decode($raw_content);

if (!isset($decoded) || !is_string($decoded) || !strlen($decoded)) {
    echo "error";
    exit();
}

$decrypted_hwid = xor_str($decoded);

if (!is_valid_sha256($decrypted_hwid)) {
    echo "error";
    exit();
}

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

$stmt = $pdo->prepare("SELECT * FROM " . $config["dbtable"] .  " WHERE ip=? OR hwid=?");
$stmt->execute([hash("sha256", $_SERVER["REMOTE_ADDR"] . $config["salt"]), $decrypted_hwid]);
$data = $stmt->fetch();

$ip_info = json_decode(file_get_contents("http://ip-api.com/php/" . $_SERVER["REMOTE_ADDR"]));

if ($data) {
    /* dont allow reconnection if it was too recent (spam protection) */
    if (time() - $data["access_time"] < 2 * 60 || $data["cooldown_time"] > time() /* check if temp banned (is it over?) */) {
        echo "disabled";
        exit();
    }

    /* already have data, but time limit expired. we can clear it and let user try again */
    $stmt = $pdo->prepare("UPDATE " . $config["dbtable"] . " SET access_time=?,cooldown_time=?,access_code=? WHERE ip=? OR hwid=?");
    $stmt->execute([time(), time(), "0", $data["ip"], $decrypted_hwid]);
}
else {
    /* add new connection info to db */
    $stmt = $pdo->prepare("INSERT INTO " . $config["dbtable"] . " (ip, access_time, cooldown_time, access_code, hwid, region) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([hash("sha256", $_SERVER["REMOTE_ADDR"] . $config["salt"]), time(), time(), "0", $decrypted_hwid, hash("sha256",$ip_info["city"] . $config["salt"])]);
}

/* clean up */
$pdo = null;
$stmt = null;
$data = null;

echo "success";
exit();

?>