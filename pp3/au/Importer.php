<?php

/**
 * Importer takes parsed data and import it to DB
 *
 */
class Importer {

    public static $ipCache;
    public static $userCache;
    public static $user2Cache;
    public static $catalogCache;
    public static $distroCache;
    public static $configCache;
    private static $_cacheLoaded = false;
    private static $_source;
    private static $_packs;

    public static function import($row, $source) {
        //die(var_dump($row));
        self::$_source = $source;
        // load up catalogs, distros, configs to cache
        if (self::$_cacheLoaded == false) {
            self::loadCache();
        }
        // let's import that
        $ip = $row['ip'];
        $ts = $row['ts'];
        $path = $row['path'];
        $distro = $row['distro'];
        $user_id = $row['user_id'];
        $user2_id = $row['user2_id'];
        $response = $row['response'];
        $size = $row['size'];

        // resolve the catalog
        if (!self::$catalogCache[$path]) {
            Db::query("INSERT INTO catalogs (path, source) VALUES ('$path', '$source')");
            $catalogId = mysqli_insert_id(Db::$link);
            self::$catalogCache[$path] = $catalogId;
        } else {
            $catalogId = self::$catalogCache[$path];
        }

        // resolve the distro
        if (preg_match('/(NB[A-Z]*)?_?([_A-Z]+)?/', $distro, $match)) {
            $distro_code = "";
            $config = "";
            $config_sig = 0;
            $warning_count = 0;

            if (isset($match[1])) {
                $distro_code = $match[1];
            }
            if (isset($match[2])) {
                $config = $match[2];
                $config_parts = explode("_", $config);
                foreach ($config_parts as $part) {
                    if (isset(self::$_packs[$part])) {
                        $config_sig += self::$_packs[$part];
                    } else {
                        $warning_count++;
                        Logger::write(Logger::INFO, "Uknown pack ID: $part; $config; $distro_code");
                    }
                }
            }
        }
        if (!self::$distroCache[$distro_code]) {
            Db::query("INSERT INTO distros (distro) VALUES ('$distro_code')");
            $distroId = mysqli_insert_id(Db::$link);
            self::$distroCache[$distro_code] = $distroId;
        } else {
            $distroId = self::$distroCache[$distro_code];
        }

        // resolve config
        if (!self::$configCache[$config]) {
            Db::query("INSERT INTO configs (config, signature) VALUES ('$config', $config_sig)");
            $configId = mysqli_insert_id(Db::$link);
            self::$configCache[$config] = $configId;
        } else {
            $configId = self::$configCache[$config];
        }

        // resolve the IP address
//        if (!self::$ipCache[$ip]) {
//            // lookup db for it
//            $res = Db::query('SELECT id FROM ips WHERE ip="' . $ip . '" LIMIT 1');
//            if ($res) {
//                if (mysqli_num_rows($res) > 0) {
//                    $row = mysqli_fetch_assoc($res);
//                    $ipId = $row['id'];
//                    self::$ipCache[$ip] = $ipId;
//                } else {
//                    Db::query("INSERT INTO ips (ip, country) VALUES ('$ip' ,'" . getCCByIP($ip) . "')");
//                    $ipId = mysqli_insert_id();
//                    self::$ipCache[$ip] = $ipId;
//                }
//            }
//        } else {
//            $ipId = self::$ipCache[$ip];
//        }
	$ipId=0;

        // resolve userId
        $tsInt = strtotime($ts);
        if (!self::$userCache[$user_id]) {
            $res = Db::query('SELECT id, since, last_seen FROM users WHERE unique_id="' . $user_id . '"');
            if ($res) {
                if (mysqli_num_rows($res) > 0) {
                    $row = mysqli_fetch_assoc($res);
                    $userId = $row['id'];
                    // if this ping is newer then one in DB, save it as last seen and calc delay
                    if ($tsInt > $row['last_seen']) {
                        // calc the delay since last ping
                        $delay = round(($tsInt - $row['last_seen']) / (60 * 60 * 24));
                        // update last seen and delay
                        if($delay>0) {
                            Db::query("UPDATE users SET last_seen=$tsInt, catalog_id=$catalogId, delay=$delay WHERE id=" . $userId);
                        }
                    }
                    self::$userCache[$user_id] = array('id' => $userId, 'since' => $row['since'], 'last_seen' => $tsInt, 'catalog_id' => $catalogId, 'delay' => $delay);
                } else {
                    Db::query("INSERT INTO users (unique_id, since, last_seen, catalog_id, delay) VALUES ('$user_id', '$ts', $tsInt, $catalogId, 9999)");
                    $userId = mysqli_insert_id(Db::$link);
                    self::$userCache[$user_id] = array('id' => $userId, 'since' => $ts, 'last_seen' => $tsInt, 'catalog_id' => $catalogId, 'delay' => 9999);
                }
            }
        } else {
            $userId=self::$userCache[$user_id]['id'];
            // check if the hit from cache has newer timestamp, if so, mark user for update her timestamp
            if (self::$userCache[$user_id]['since'] > $ts) {
                $updateUsers = array('id' => self::$userCache[$user_id]['id'], 'since' => $ts);
            }
        }

        // resolve userId
        if (!self::$user2Cache[$user2_id]) {
            $res = Db::query('SELECT id, since, last_seen FROM users2 WHERE unique_id="' . $user2_id . '"');
            if ($res) {
                if (mysqli_num_rows($res) > 0) {
                    $row = mysqli_fetch_assoc($res);
                    $user2Id = $row['id'];
                    // if this ping is newer then one in DB, save it as last seen and calc delay
                    if ($tsInt > $row['last_seen']) {
                        // calc the delay since last ping
                        $delay = round(($tsInt - $row['last_seen']) / (60 * 60 * 24));
                        // update last seen and delay
                        if($delay>0) {
                            Db::query("UPDATE users2 SET last_seen=$tsInt, delay=$delay WHERE id=" . $user2Id);
                        }
                    }
                    self::$user2Cache[$user2_id] = array('id' => $user2Id, 'since' => $row['since'], 'last_seen' => $tsInt, 'delay' => $delay);
                } else {
                    Db::query("INSERT INTO users2 (unique_id, since, last_seen, delay) VALUES ('$user2_id', '$ts', $tsInt,9999)");
                    $user2Id = mysqli_insert_id(Db::$link);
                    self::$user2Cache[$user2_id] = array('id' => $user2Id, 'since' => $ts, 'last_seen' => $tsInt, 'delay' => 9999);
                }
            }
        } else {
            $user2Id=self::$user2Cache[$user2_id]['id'];
            // check if the hit from cache has newer timestamp, if so, mark user for update her timestamp
            if (self::$user2Cache[$user2_id]['since'] > $ts) {
                $updateUsers2 = array('id' => self::$user2Cache[$user2_id]['id'], 'since' => $ts);
            }
        }


        // now save it to hits table finally
        $res = Db::query("INSERT INTO pings (ip_id, ts, path_id, distro_id, config_id, user_id, user2_id, response, size) VALUES ($ipId, '$ts', $catalogId, $distroId, $configId, $userId, $user2Id, $response, $size)");
        if ($res) {
            $ret = true;
        } else {
            $ret = false;
        }

        // now update users since timestamps (might be case if we back import older logs)
        if (!empty($updateUsers)) {
            foreach ($updateUsers as $u) {
                Db::query("UPDATE users SET since='" . $u['since'] . "' WHERE id=" . $u['id']);
            }
        }
        if (!empty($updateUsers2)) {
            foreach ($updateUsers2 as $u) {
                Db::query("UPDATE users2 SET since='" . $u['since'] . "' WHERE id=" . $u['id']);
            }
        }
        return $ret;
    }

    private static function loadCache() {
        $memBeforeCache = memory_get_usage();
        $res = Db::query('SELECT id, path FROM catalogs WHERE source="' . self::$_source . '"');
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                self::$catalogCache[$r['path']] = $r['id'];
            }
        }
        $res = Db::query('SELECT id, config FROM configs');
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                self::$configCache[$r['config']] = $r['id'];
            }
        }
        $res = Db::query('SELECT id, distro FROM distros');
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                self::$distroCache[$r['distro']] = $r['id'];
            }
        }

        // log some stats on the memory usage so we know how is the cache expensive
        Logger::write(Logger::INFO, 'Caching distros, catalogs, configs took ' . round((memory_get_usage() - $memBeforeCache) / 1024000, 1) . 'MB');

        self::$_packs = array(
            'CND' => 0x0001,
            'CRE' => 0x0002,
            'ENT' => 0x0004,
            'MOB' => 0x0008,
            'PROF' => 0x0010,
            'CDC' => 0x0020,
            'CLDC' => 0x0040,
            'JAVA' => 0x0080,
            'JAVASE' => 0x0100,
            'JAVAEE' => 0x0200,
            'JAVAME' => 0x0400,
            'WEBEE' => 0x0800,
            'PROFILER' => 0x1000,
            'PHP' => 0x2000,
            'RUBY' => 0x4000,
            'MOBILITY' => 0x8000,
            'UML' => 0x10000,
            'SOA' => 0x20000,
            'GLASSFISH' => 0x40000,
            'SJSAS' => 0x80000,
            'TOMCAT' => 0x100000,
            'VISUALWEB' => 0x200000,
            'JDK' => 0x400000,
            'MYSQL' => 0x800000,
            'GROOVY' => 0x1000000,
            'GFMOD' => 0x2000000,
            'JAVAFX' => 0x4000000,
            'WEBCOMMON' => 0x8000000,
            'FX' => 0x10000000,
            'PY' => 0x20000000,
            'JC' => 0x40000000,
            'WEBLOGIC' => 0x80000000,
            'JAVAFXSDK' => 0x100000000,
            'NB' => 0x200000000,
            'EXTIDE' => 0x400000000
            );
self::$_cacheLoaded = true;
}

}

?>
