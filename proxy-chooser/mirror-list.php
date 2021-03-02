<?php

require("classes/ApacheMirrors.php");
require("classes/IP2Location.php");
require("classes/CountryCodes.php");
require("classes/config.php");

header("X-Attribution: This site or product includes IP2Proxy LITE data available from https://lite.ip2location.com");

// Initialize mirror list
$mirrors = new ApacheMirrors($CONFIG['mirrorListUrl'], $CONFIG['mirrorCacheFile']);
$countryCodes = new CountryCodes();

if(isset($_REQUEST['countryCode'])) {
    $countryCode = $_REQUEST['countryCode'];
} else {
    $database = new IP2Location\Database(__DIR__ . "/data/IP2LOCATION-LITE-DB1.BIN");
    $database2 = new IP2Location\Database(__DIR__ . "/data/IP2LOCATION-LITE-DB1.IPV6.BIN");

    $ip4result = $database->lookup($_SERVER['REMOTE_ADDR']);
    $ip6result = $database2->lookup($_SERVER['REMOTE_ADDR']);

    $detectedCountryCode = "";
    if ($ip6result) {
        $detectedCountryCode = $ip6result['countryCode'];
    } else if ($ip4result) {
        $detectedCountryCode = $ip4result['countryCode'];
    }

    $countryCode = $countryCodes->resolveApacheCodeFromIP2Country($detectedCountryCode);
}

$countryName = $countryCodes->resolveNameApache($countryCode);

?>
<!DOCTYPE html>
<html>
    <head>
        <title>Update-Center Proxy Chooser</title>
        <style>
            * {
                font-family: Arial, sans-serif;
            }
        </style>
    </head>
    <body>
        <h1>Update-Center Proxy Chooser</h1>
        <p>This site allows you to create customized update center URLs for the NetBeans IDE. By default the update center for the NetBeans IDE will use a random apache mirror based on your country.</p>
        <p><form action="" method="GET">
            Mirrors for:
            <select name="countryCode">
                <?php foreach($mirrors->getCountryCodes() as $code) { 
                    printf('<option %s value="%s">%s</option>'
                            , $code == $countryCode ? 'selected="selected"' : ""
                            , htmlspecialchars($code, ENT_COMPAT, "UTF-8")
                            , htmlspecialchars($countryCodes->resolveNameApache($code), ENT_COMPAT, "UTF-8")
                            );
                } ?>
            </select>
            <input type="submit" value="Update" />
        </form></p>
        <h2>Usage</h2>
        <ol>
            <li>Check if the correct country was detected, if not choose the correct country above and update.</li>
            <li>Choose one of the available mirrors and copy the url to the clipboard.</li>
            <li>Open NetBeans and go to <em>Tools</em> &#x2192; <em>Plugins</em> &#x2192; <em>Settings</em>.</li>
            <li>Add a new entry:<ul>
                    <li>Set name to "NetBeans Distribution (Mirrored)".</li>
                    <li>Ensure "Check of updates automatically" is checked.</li>
                    <li>Insert the copied URL and replace "version" in the URL with your NetBeans version.</li>
                    <li>Ensure "Trust update center fully and and allow automatic installations" is checked.</li>
                </ul>
            </li>
            <li>Switch to the "Updates" tab and choose "Check for updates".</li>
        </ol>
        <h2>Mirrors for <?php echo htmlspecialchars($countryName . " - " . $countryCode, ENT_COMPAT, "UTF-8"); ?></h2>
        <ul>
            <?php foreach($mirrors->getMirrorsForCountry($countryCode) as $mirror) {
                $url = sprintf($CONFIG['updateCenterUrl']
                        , htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, "UTF-8")
                        , htmlspecialchars($mirror, ENT_COMPAT, "UTF-8"));
                printf("<li>%s:<br><em>%s</em></li>",
                        htmlspecialchars($mirror, ENT_COMPAT, "UTF-8"),
                        $url);
            }?>
        </ul>
        <p style="font-size: 80%">This site or product includes IP2Proxy LITE data available from <a href="https://lite.ip2location.com">https://lite.ip2location.com</a></p>
    </body>
</html>