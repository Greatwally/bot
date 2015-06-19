<?php
require_once "Spyc.php";

class PingThread extends Thread {

    public function __construct($config)
    {
        $this->config = $config;
        $this->start();
    }

    public function run()
    {
        while(true) {
            $session = curl_init();
            curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($session, CURLOPT_TIMEOUT, 10);
            curl_setopt($session, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($session, CURLOPT_URL, "https://csgo.tm/api/PingPong/?key=" . $this->config['secret_key']);
            curl_exec($session);
            sleep(150);
        }
    }
}


class WorkerThread extends Thread {
    private $item;
    private $config;

    public function __construct($item, $config, $proxy = null)
    {
        $this->item = $item;
        $this->proxy = $proxy;
        $this->config = $config;
        $this->start();
    }

    public function run()
    {
        date_default_timezone_set('Asia/Dhaka');
        $session = curl_init();
        curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($session, CURLOPT_TIMEOUT, 10);
        curl_setopt($session, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);

        if ($this->proxy) {
            curl_setopt($session, CURLOPT_PROXYPORT, $this->proxy['port']);
            curl_setopt($session, CURLOPT_PROXY, $this->proxy['ip']);

            if ($this->proxy['proxyType'] == 'sock5') {
                curl_setopt($session, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }

        }

        while (true) {
            curl_setopt($session, CURLOPT_URL, "https://csgo.tm/api/ItemInfo/{$this->item['classid']}_{$this->item['instanceid']}/ru/?key=1");
            $data = json_decode(curl_exec($session), true);
            if (json_last_error() != 0) {
                $jsonError = "json_last_error - " . json_last_error() . "\n";
                echo $jsonError;
                file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'bot.log', $jsonError, FILE_APPEND);
                continue;
            }

            if (!$data) {
                $proxyError = "Looks like this proxy {$this->proxy['ip']}:{$this->proxy['port']} is dead\n";
                echo $proxyError;
                file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'bot.log', $proxyError, FILE_APPEND);
                continue;
            }

            if ($data['min_price'] == -1) {
                continue;
            }

            foreach ($data['offers'] as $offer) {
                if ($offer['price'] <= $this->item['price']) {
                    curl_setopt($session, CURLOPT_URL, "https://csgo.tm/api/Buy/{$this->item['classid']}_{$this->item['instanceid']}/{$offer['price']}/{$data['hash']}/?key=" . $this->config['secret_key']);
                    $answerAfterBuy = serialize(json_decode(curl_exec($session), true)) . "\n";
                    echo $answerAfterBuy;
                    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'bot.log', $answerAfterBuy, FILE_APPEND);
                    $logString = "[" . date("Y-m-d H:i:s") . "]" . " just bought {$data['name']} for {$offer['price']}\n";
                    echo $logString;
                    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'bot.log', $logString, FILE_APPEND);
                }
            }
        }
        curl_close($session);
    }
}

if (!is_writable(__DIR__)) {
    echo "Permission denied\n";
    die;
};

if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'bot.log')) {
    touch(__DIR__ . DIRECTORY_SEPARATOR . 'bot.log');
}

if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'config.yml')) {
    echo "config.yml doesn't exist\n";
    die;
}



$config = Spyc::YAMLLoad('config.yml');
$items = Spyc::YAMLLoad('items.yml');


unset($items['items']);

$countProxies = ceil(count($items) / 4) - 1;
if (count($config['proxies']) < $countProxies) {
    echo "Quantity of proxies should be at least $countProxies \n";
    die;
}

$pingThread = new PingThread($config);

$proxies_ = $config['proxies'];
$proxies = [];
for ($i = 0; $i < count($proxies_); $i++) {
    $item = explode(':', $proxies_[$i]);
    $proxies[$i]['ip'] = $item[0];
    $proxyType = explode('_', $item[1]);
    $proxies[$i]['port'] = $proxyType[0];
    $proxies[$i]['proxyType'] = $proxyType[1];
}

$threads = [];
$j = 0;
$k = 0;
array_unshift($proxies, null);


for ($i = 0; $i <= count($items) - 1; $i++) {
    if ($j > 3) {
        $k++;
        $j = 0;
    } else {
        $j++;
    }
    $proxy = $proxies[$k];

    $threads[$i] = new WorkerThread($items[$i], $config, $proxy);
    echo "Started $i\n";

}

foreach ($threads as $thread) {
    $thread->join();
}
