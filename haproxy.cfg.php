<?php

$hleConfPath = '/etc/hle';
$pemPath = '/etc/hle/pem';

$accountList = [];
if ($handle = opendir($hleConfPath)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry !== '.' && $entry !== '..' && is_file($hleConfPath . '/' . $entry)) {
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
?>
global
    log /dev/log    local0
    log /dev/log    local1 notice
    maxconn 102400
    nbproc 1
    pidfile /var/run/haproxy.pid

    # SSL Option
    tune.ssl.default-dh-param  2048
    ssl-default-bind-options no-sslv3 no-tls-tickets
    ssl-default-bind-ciphers ECDH+AESGCM:DH+AESGCM:ECDH+AES256:DH+AES256:ECDH+AES128:DH+AES:RSA+AESGCM:RSA+AES:!aNULL:!MD5:!DSS:!RSA-DES-CBC-SHA:!DHE-RSA-3DES-EDE-CBC-SHA:!DHE-RSA-AES128-GCM-SHA256:!DHE-RSA-CAMELLIA256-CBC-SHA:!DHE-RSA-CAMELLIA128-CBC-SHA

defaults
    log     global
    mode    http
    option  dontlognull
    timeout server 1200s
    timeout connect 1200s
    timeout client 1200s
    retries 3
    unique-id-format %{+X}o\ %ci:%cp_%fi:%fp_%Ts_%rt:%pid
    log-format [%ID]\ %ci:%cp\ [%t]\ %f\ %b/%s\ %Tq/%Tw/%Tc/%Tr/%Tt\ %ST\ %B\ %CC\ %CS\ %tsc\ %ac/%fc/%bc/%sc/%rc\ %sq/%bq\ {%hrl}\ {%hsl}\ %{+Q}r

frontend http_redirect
    bind *:80
    mode http
    option forwardfor

    # capture X-Forwarded-For to log variable
    capture request header X-Forwarded-For len 15

    # haproxy stats
    stats enable
    stats uri /stats
    stats hide-version
    stats auth admin:admin
    stats realm SSL-Proxy
    stats refresh 10s

    # .well-known URL prefix
    acl url_well   url_beg   /.well-known
    use_backend local_letsencrypt if url_well

    # Insert a unique request identifier is the headers of the request
    unique-id-header X-Unique-ID

    # vhost dispatch
<?php
foreach ($accountList as &$account) {
    foreach ($account['domain'] as &$hostname) {
        echo '    acl host_' . $account['domain'][0] . ' hdr(host) -i ' . $hostname . "\n";
    }
    echo '    use_backend cluster_' . $account['domain'][0] . ' if host_' . $account['domain'][0] . "\n";
}
?>

    # default backend
    default_backend local_nginx

<?php
$crtConfString = '';
foreach ($accountList as &$account) {
    foreach ($account['domain'] as &$hostname) {
        $crtFilePath = $pemPath  . '/' . $account['domain'][0] . '.pem';
        if (is_file($crtFilePath)) $crtConfString .= 'crt ' . $crtFilePath . ' ';
    }
}
?>
frontend https_frontend
    bind *:443 ssl crt /etc/haproxy/ssl.pem <?php echo $crtConfString;?> ciphers ECDHE+aRSA+AES256+GCM+SHA384:ECDHE+aRSA+AES128+GCM+SHA256:ECDHE+aRSA+AES256+SHA384:ECDHE+aRSA+AES128+SHA256:ECDHE+aRSA+RC4+SHA:ECDHE+aRSA+AES256+SHA:ECDHE+aRSA+AES128+SHA:AES256+GCM+SHA384:AES128+GCM+SHA256:AES128+SHA256:AES256+SHA256:DHE+aRSA+AES128+SHA:RC4+SHA:HIGH:!aNULL:!eNULL:!LOW:!3DES:!MD5:!EXP:!PSK:!SRP:!DSS
    mode http
    option forwardfor

    # capture X-Forwarded-For to log variable
    capture request header X-Forwarded-For len 15

    # haproxy stats
    stats enable
    stats uri /stats
    stats hide-version
    stats auth admin:admin
    stats realm SSL-Proxy
    stats refresh 10s

    http-request set-header X-Forwarded-Proto https

    # Insert a unique request identifier is the headers of the request
    unique-id-header X-Unique-ID

    # vhost dispatch
<?php
foreach ($accountList as &$account) {
    foreach ($account['domain'] as &$hostname) {
        echo '    acl host_' . $account['domain'][0] . ' hdr(host) -i ' . $hostname . "\n";
    }
    echo '    use_backend cluster_' . $account['domain'][0] . ' if host_' . $account['domain'][0] . "\n";
}
?>

    # default backend
    default_backend local_nginx


backend local_nginx
    option redispatch
    http-request del-header Strict-Transport-Security
    http-request set-header X-Forwarded-Port %[dst_port]
    http-request set-header X-Forwarded-Host %[hdr(host)]
    server nginx 127.0.0.1:8080 check

backend local_letsencrypt
    option redispatch
    server nginx 127.0.0.1:88 check

<?php
foreach ($accountList as &$account) {
    echo 'backend cluster_' . $account['domain'][0] . "\n";
    echo '    option redispatch' . "\n";
    echo '    http-request set-header X-Forwarded-Port %[dst_port]' . "\n";
    echo '    http-request set-header X-Forwarded-Host %[hdr(host)]' . "\n";
    foreach ($account['server'] as $i => $server) {
        echo '    server ' . 'node-' . ($i + 1) . '_' . $server . ' ' . $server ." check\n";
    }
    echo "\n";
}
?>
