<?php
require_once "Spyc.php";

class PingThread extends Thread {

    public function __construct($config)
    {
        $this->config = $config;
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

    private $items;
    private $config;

    public function __construct($items, $config)
    {
        $this->items = $items;
        $this->config = $config;
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

        while(true) {
            foreach ($this->items as $item) {
                curl_setopt($session, CURLOPT_URL, "https://csgo.tm/api/ItemInfo/{$item['classid']}_{$item['instanceid']}/ru/?key=1");
                $data = json_decode(curl_exec($session), true);
                if (json_last_error() != 0) {
                    echo "json_last_error - " . json_last_error() . "\n";
                } else {
                    if ($data['min_price'] == -1) {
                        break;
                    }
                    foreach($data['offers'] as $offer) {
                        if ($offer['price'] <= $item['price']) {
                            curl_setopt($session, CURLOPT_URL, "https://csgo.tm/api/Buy/{$item['classid']}_{$item['instanceid']}/{$offer['price']}/{$data['hash']}/?key=" . $this->config['secret_key']);
                            $answerAfterBuy = serialize(json_decode(curl_exec($session), true)) . "\n";
                            echo serialize($answerAfterBuy);
                            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'bot.log', $answerAfterBuy, FILE_APPEND);
                            $logString = "[" . date("Y-m-d H:i:s") . "]" . " just bought {$data['name']} for {$offer['price']}\n";
                            echo $logString;
                            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'bot.log', $logString, FILE_APPEND);
                        }
                    }
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
$items = array_chunk($items, ceil(count($items) / 4), false);


$pingThread = new PingThread($config);
$pingThread->start();

$i = 0;
$threads = [];

foreach ($items as $item) {
    $threads[$i] = new WorkerThread($item, $config);
    $threads[$i]->start();
    $i++;
}

foreach ($threads as $thread) {
    $thread->join();
}