
.
<?
function is_banned($hwid) {
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

    $stmt = $pdo->prepare("SELECT * FROM " . $config["dbtable"] . " WHERE ip=? OR hwid=?");
    $stmt->execute([hash("sha256", $_SERVER["REMOTE_ADDR"] . $config["salt"]), $hwid]);
    $data = $stmt->fetch();

    if ($data) {
        /* ADD CHECK FOR PERM BAN HERE */
        if ($data["cooldown_time"] > time() /* check if temp banned (is it over?) */) {
            return true;
        }

        $ip_info = json_decode(file_get_contents("http://ip-api.com/php/" . $_SERVER["REMOTE_ADDR"]));

        if (time() - $data["access_time"] > 5 /* check if we havent gotten a response within a while */
        || hash("sha256", $ip_info["city"] . $config["salt"]) != $data["region"] /* check if location of ip address suddenly changed (vpn?) */ ) {
            $stmt = $pdo->prepare("UPDATE " . $config["dbtable"] . " SET access_time=?,cooldown_time=? WHERE ip=? OR hwid=?");
            $stmt->execute([time(), time() + 2 * 60, hash("sha256", $_SERVER["REMOTE_ADDR"] . $config["salt"]), $hwid]);
            return true;
        }
        
        return false;
    }

    return true;
}
?>