<?php

require_once __DIR__ . '/vendor/autoload.php'; //Path to composer autoload

$accountList = [];
$hleConfPath = '/etc/hle';
if ($handle = opendir($hleConfPath)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            if (!filter_var($entry, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('File name not a email format.');
            }
            $accountConf = json_decode(file_get_contents($hleConfPath . '/' . $entry), true);
            foreach ($accountConf as &$conf) {
                if (!isset($conf['domain'])) throw new Exception('"domain" undefined.');
                if (!isset($conf['server'])) throw new Exception('"server" undefined.');
                $accountList[] = [
                    'email' => $entry,
                    'domain' => $conf['domain'],
                    'server' => $conf['server']
                ];
            }
        }
    }
    closedir($handle);
}

// Config the desired paths
\LE_ACME2\Account::setCommonKeyDirectoryPath('/etc/ssl/le-storage/');
\LE_ACME2\Order::setHTTPAuthorizationDirectoryPath('/var/www/letsencrypt/.well-known/acme-challenge/');

// General configs
\LE_ACME2\Connector\Connector::getInstance()->useStagingServer((getenv('HLE_STAGING') === 'true'));
\LE_ACME2\Utilities\Logger::getInstance()->setDesiredLevel(\LE_ACME2\Utilities\Logger::LEVEL_INFO);


$needRestart = false;
foreach ($accountList as $accountInfo) {

    $account = !\LE_ACME2\Account::exists($accountInfo['email']) ?
        \LE_ACME2\Account::create($accountInfo['email']) :
        \LE_ACME2\Account::get($accountInfo['email']);

    $subjects = $accountInfo['domain'];

    echo "Account: ${accountInfo['email']}\n";
    echo 'Domain: ' . implode(', ', $subjects) . "\n";

    $order = !\LE_ACME2\Order::exists($account, $subjects) ?
        \LE_ACME2\Order::create($account, $subjects) :
        \LE_ACME2\Order::get($account, $subjects);

    if ($order->authorize(\LE_ACME2\Order::CHALLENGE_TYPE_HTTP)) {
        $order->finalize();
    }

    if ($order->isCertificateBundleAvailable()) {
        $bundle = $order->getCertificateBundle();

        $pemFilepath = '/etc/haproxy/' . $accountInfo['domain'][0] . '.pem';
        $haproxyPemContent = '';
        $haproxyPemContent .= file_get_contents($bundle->path . $bundle->certificate);
        $haproxyPemContent .= file_get_contents($bundle->path . $bundle->intermediate) . "\n";
        $haproxyPemContent .= file_get_contents($bundle->path . $bundle->private);
        $currentMd5 = md5($haproxyPemContent);
        $oldMd5 = is_file($pemFilepath . '.md5') ? file_get_contents($pemFilepath . '.md5') : '';
        if ($oldMd5 !== $currentMd5) {
             file_put_contents($pemFilepath . '.md5', $currentMd5);
             file_put_contents($pemFilepath, $haproxyPemContent);
             echo "SSL pem file renew. ($pemFilepath)\n";
             $needRestart |= true;
        }
        $order->enableAutoRenewal();
    }

}

# make haproxy config and check
echo "Make haproxy.cfg file...\n";
exec('php /usr/share/hle/haproxy.cfg.php > /etc/haproxy/haproxy.cfg', $output, $returnCode);
if ($returnCode !== 0) throw new Exception("HAProxy config file make fail...\n");
$currentHaproxyMd5 = md5(file_get_contents('/etc/haproxy/haproxy.cfg'));
$oldHAproxyMd5 = file_get_contents('/etc/haproxy/haproxy.cfg.md5');
if ($currentHaproxyMd5 !== $oldHAproxyMd5) {
     $needRestart = true;
}

if ($needRestart) {
    exec('haproxy -c -f /etc/haproxy/haproxy.cfg 2>&1', $output, $returnCode);
    if ($returnCode !== 0) throw new Exception(implode("\n", $output) . "\n");

    echo "Restart HAProxy...\n";
    file_put_contents('/etc/haproxy/haproxy.cfg.md5', $currentHaproxyMd5);
    passthru('/usr/bin/supervisorctl restart haproxy');
} else {
        echo "SSL pem file no change, skip renew.\n";    
}
