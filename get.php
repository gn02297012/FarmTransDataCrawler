<pre><?php
    //設定執行時間上限
    set_time_limit(0);
    //將網頁編碼設定為UTF8
    header("Content-type: text/html; charset=utf-8");

    /**
     * 將時間格式化成民國年月日的字串
     * @param int $timestamp 時間
     * @return string 民國年.月.日
     */
    function formatDate($timestamp) {
        $d = getdate($timestamp);
        $y = $d['year'] - 1911;
        return $y . date('.m.d', $timestamp);
    }

    /**
     * 呼叫API下載資料
     * @param int $t 時間
     * @return string 原始資料
     */
    function get($t) {
        //將時間格式化成API接受的格式
        $date = formatDate($t);
        //使用CURL呼叫
        $ch = curl_init();
        $options = array(CURLOPT_URL => "http://m.coa.gov.tw/OpenData/FarmTransData.aspx?\$top=10000&\$skip=0&StartDate={$date}&EndDate={$date}",
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
        );
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        echo "API查詢URL:\t{$info['url']}\n";
        echo "下載花費時間:\t{$info['total_time']}sec\n";
        return $result;
    }

    /**
     * 建立資料庫連線
     * @return \mysqli
     */
    function &connectDB() {
        //載入DB設定
        require('db_config.php');
        $mysqli = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['db'], $db_config['port']);
        if ($mysqli->connect_errno) {
            die("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
        }
        return $mysqli;
    }

    /**
     * 呼叫API並將資料解析成JSON陣列
     * @param int $t 時間
     * @return array JSON格式的資料
     */
    function &parseJSON($t) {
        $result = get($t);
        $json = json_decode($result, true);
        $count = count($json);
        echo "解析JSON完成!共有{$count}筆資料\n";
        ob_flush();
        flush();
        return $json;
    }

    /**
     * 執行爬蟲程式
     * @param int $t 最後一天的時間
     * @param int $c 要查詢多少天
     */
    function run($t, $c) {
        //連線到資料庫
        $mysqli = connectDB();
        //設定文字編碼
        $mysqli->set_charset("utf8");
        for ($i = 0; $i < $c; $i++) {
            //取得JSON格式的資料
            $array = parseJSON($t);
            //寫入資料庫
            $start_time = microtime(true);
            foreach ($array as &$item) {
                $stmt = $mysqli->prepare("INSERT INTO `farmtransdata`.`raw` (`date`, `code`, `name`, `marketCode`, `market`, `priceTop`, `priceMid`, `priceBottom`, `price`, `quantity`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
                $stmt->bind_param('sssssddddi', $item['交易日期'], $item['作物代號'], $item['作物名稱'], $item['市場代號'], $item['市場名稱'], $item['上價'], $item['中價'], $item['下價'], $item['平均價'], $item['交易量']);
                $stmt->execute();
                $stmt->close();
            }
            echo date('Y-m-d', $t) . "的所有資料寫入資料庫完成\n";
            echo "寫入資料花費時間:\t" . (microtime(true) - $start_time) . "sec\n\n";
            //將時間往前一天
            $t -= 86400;
        }
        //關閉資料庫連線
        $mysqli->close();
    }

    //要進行查詢的那一天，如果要查詢多天，則此參數必須設定為最後一天的日期
    $t = isset($_GET['d']) ? strtotime($_GET['d']) : time();
    //總共要查詢幾天，程式會根據另一個參數的日期往前查詢
    $c = isset($_GET['c']) ? (int) $_GET['c'] : 1;
    //如果要查詢2014-07-01~2014-07-31的資料，則以上兩個參數必須分別是2014-07-31與31
    //執行
    run($t, $c);
    ?>
</pre>