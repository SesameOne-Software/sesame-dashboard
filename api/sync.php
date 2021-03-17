<?
/* connection will only be allowed to survive if the client can respond correctly to the prompts */
function rand_str($length) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomString;
}

if ($_SERVER["REQUEST_METHOD"] != "POST" || $_SERVER["HTTP_USER_AGENT"] != "5club-loader-client") {
    echo "error";
    exit();
}

$config = require("config.php");
require_once("banned.php");
require_once("util.php");

$raw_content = file_get_contents("php://input");

if (!isset($raw_content) || !is_string($raw_content) || !strlen($raw_content)) {
    echo "error";
    exit();
}

$parts = explode('.', $raw_content);
$response = $parts[0];
$decoded = base64_decode($parts[1]);

if (!isset($decoded) || !is_string($decoded) || !strlen($decoded)) {
    echo "error";
    exit();
}

$decrypted_hwid = xor_str($decoded);

if (!is_valid_sha256($decrypted_hwid)) {
    echo "error";
    exit();
}

if (is_banned($decrypted_hwid)) {
    echo "disabled";
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
$stmt->execute([hash("sha256", $_SERVER["REMOTE_ADDR"]. $config["salt"]), $decrypted_hwid]);
$data = $stmt->fetch();

if (!$data) {
    echo "error";
    exit();
}

if ($data["access_code"] == "0" && (empty($response) || strlen($response) <= 0)) {
    $new_response = base64_encode(rand_str(128));

    $stmt = $pdo->prepare("UPDATE " . $config["dbtable"] . " SET access_time=?,cooldown_time=?,access_code=? WHERE ip=? OR hwid=?");
    $stmt->execute([time(), time(), $new_response, hash("sha256", $_SERVER["REMOTE_ADDR"]. $config["salt"]), $decrypted_hwid]);

    echo base64_encode(xor_str($new_response));
    exit();
}
else if (strlen($data["access_code"]) > 0 && (!empty($response) && strlen($response) > 0)) {
    if ($data["access_code"] == $response) {
        $new_response = base64_encode(rand_str(128));

        $stmt = $pdo->prepare("UPDATE " . $config["dbtable"] . " SET access_time=?,cooldown_time=?,access_code=? WHERE ip=? OR hwid=?");
        $stmt->execute([time(), time(), $new_response, hash("sha256", $_SERVER["REMOTE_ADDR"] . $config["salt"]), $decrypted_hwid]);

        echo base64_encode(xor_str($new_response));
        exit();
    }
}

/* temp ban for invalid response (probably was modified or mimicked unauthorized) */
$stmt = $pdo->prepare("UPDATE " . $config["dbtable"] . " SET access_time=?,cooldown_time=? WHERE ip=? OR hwid=?");
$stmt->execute([time(), time() + 10 * 60, hash("sha256", $_SERVER["REMOTE_ADDR"] . $config["salt"]), $decrypted_hwid]);

echo "disabled";
exit();

?>