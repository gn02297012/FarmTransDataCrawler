<pre><?php
    //設定執行時間上限
    set_time_limit(0);
    //將網頁編碼設定為UTF8
    header("Content-type: text/html; charset=utf-8");

    /**
     * 爬蟲程式的Class
     */
    class Crawler {

        /**
         * 連接資料庫的物件，使用mysqli
         * @var mysqli
         */
        private $db;

        public function __construct() {
            //連線到資料庫
            $this->db = $this->connectDB();
            //設定文字編碼
            $this->db->set_charset("utf8");
        }

        public function __destruct() {
            //關閉資料庫
            $this->db->close();
        }

        /**
         * 建立資料庫連線
         * @return \mysqli
         */
        private function &connectDB() {
            //載入DB設定
            require_once('db_config.php');
            $mysqli = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['db'], $db_config['port']);
            if ($mysqli->connect_errno) {
                die("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
            }
            return $mysqli;
        }

        /**
         * 將時間格式化成民國年月日的字串
         * @param int $timestamp 時間
         * @return string 民國年.月.日
         */
        public function formatDate($timestamp) {
            $d = getdate($timestamp);
            $y = $d['year'] - 1911;
            return $y . date('.m.d', $timestamp);
        }

        public function printLog($str) {
            echo $str;
            ob_flush();
            flush();
        }

        /**
         * 執行SQL查詢
         * @param type $sql 要查詢的SQL指令
         * @return type
         */
        public function query($sql) {
            $result = $this->db->query($sql);
            $rows = array();
            //在使用DELETE的查詢時，$result的型態會變成bool，所以此處要多做檢查
            if ($result and $result instanceof mysqli_result) {
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
            }
            return $rows;
        }

        /**
         * 取得資料庫出最新一筆資料的date_int
         * @return int date_int
         */
        public function getMaxDate() {
            $query = "SELECT MAX(`date_int`) AS `date_int` FROM `raw` WHERE 1=1;";
            $result = $this->query($query);
            if (empty($result) or empty($result[0]['date_int'])) {
                return false;
            }
            return $result[0]['date_int'];
        }

        /**
         * 取得今天的民國日期整數，例如1030908
         * @return int date
         */
        public function getTodayInt() {
            $date = new DateTime("now");
            $date->modify("-1911 year");
            return (int) $date->format("Ymd");
        }

        /**
         * 刪除資料庫中，最新日期的資料，用來確保今天以前的資料都是完整的，因為每一天的資料大約要到晚上六點才可能完整，在這之前取到的資料可能都是不完整的。
         * @return boolean 是否刪除成功
         */
        public function deleteMaxDate() {
            $maxDate = $this->getMaxDate();
            if (!$maxDate) {
                return false;
            }
            $query = "DELETE FROM `raw` WHERE `date_int` = {$maxDate};";
            $this->printLog("執行刪除指令 {$query}\n\n");
            $this->query($query);
            return true;
        }

        /**
         * 將日期整數轉成可解析成日期物件的字串
         * @param int $dateInt
         * @return string 日期字串
         */
        public function &DateIntToDateStr($dateInt) {
            //將年月日從整數中取出來
            $day = $dateInt % 100;
            $day < 10 and $day = "0$day";
            $dateInt = $dateInt / 100;
            $month = (int) $dateInt % 100;
            $month < 10 and $month = "0$month";
            $dateInt /= 100;
            $year = (int) $dateInt;
            //組合出可以被解析成日期的字串
            $str = "{$year}-{$month}-{$day}";
            return $str;
        }

        /**
         * 將日期整數轉成DateTime物件
         * @param int $dateInt
         * @return \DateTime
         */
        public function &DateIntToDateTime($dateInt) {
            $str = $this->DateIntToDateStr($dateInt);
            $date = new DateTime($str);
            return $date;
        }

        /**
         * 呼叫API下載資料
         * @param int $t 時間
         * @return string 原始資料
         */
        private function get($t) {
            //將時間格式化成API接受的格式
            $date = $this->formatDate($t);
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
            $this->printLog("API查詢URL:\t{$info['url']}\n");
            $this->printLog("下載花費時間:\t{$info['total_time']}sec\n");
            return $result;
        }

        /**
         * 呼叫API並將資料解析成JSON陣列
         * @param int $t 時間
         * @return array JSON格式的資料
         */
        private function &parseJSON($t) {
            $result = $this->get($t);
            $json = json_decode($result, true);
            return $json;
        }

        /**
         * 執行爬蟲程式
         * @param int $t 最後一天的時間
         * @param int $c 要查詢多少天
         */
        public function run($t, $c) {
            $this->printLog("開始執行爬蟲程式\n");
            $this->printLog("參數t={$t}\n參數c={$c}\n\n");
            for ($i = 0; $i < $c; $i++) {
                //取得JSON格式的資料
                $array = $this->parseJSON($t);
                $count = count($array);
                $this->printLog("解析JSON完成!共有{$count}筆資料\n");
                ob_flush();
                flush();
                //寫入資料庫
                $start_time = microtime(true);
                $query = "INSERT INTO `farmtransdata`.`raw` (`date`, `code`, `name`, `marketCode`, `market`, `priceTop`, `priceMid`, `priceBottom`, `price`, `quantity`, `date_int`) VALUES ";
                $tmp = array();
                foreach ($array as &$item) {
                    $date_int = str_replace('.', '', $item['交易日期']);
                    $tmp[] = "('{$item['交易日期']}', '{$item['作物代號']}', '{$item['作物名稱']}', '{$item['市場代號']}', '{$item['市場名稱']}', {$item['上價']}, {$item['中價']}, {$item['下價']}, {$item['平均價']}, {$item['交易量']}, {$date_int})";
                }
                $this->db->query($query . implode(",", $tmp));
                $this->printLog(date('Y-m-d', $t) . "的所有資料寫入資料庫完成\n");
                $this->printLog("寫入資料花費時間:\t" . (microtime(true) - $start_time) . "sec\n\n");
                ob_flush();
                flush();
                //將時間往前一天
                $t -= 86400;
            }
        }

    }

    //是否要執行自動模式，去判斷要從哪天開始抓以及要抓幾天
    $autoMode = isset($_GET['automode']) ? $_GET['automode'] : true;
    //要進行查詢的那一天，如果要查詢多天，則此參數必須設定為最後一天的日期
    $t = isset($_GET['d']) ? strtotime($_GET['d']) : time();
    //總共要查詢幾天，程式會根據另一個參數的日期往前查詢
    $c = isset($_GET['c']) ? (int) $_GET['c'] : 1;

    //如果要查詢2014-07-01~2014-07-31的資料，則以上兩個參數必須分別是2014-07-31與31
    $crawler = new Crawler();

    $crawler->printLog("程式啟動\n");
    //是否使用自動模式，也就是由程式判斷要取幾天的資料
    if ($autoMode) {
        $crawler->printLog("使用自動模式\n\n");
        //刪除最新兩天的資料
        for ($i = 0; $i < 2; $i++) {
            //假如刪除失敗就離開迴圈，避免浪費時間
            if (!$crawler->deleteMaxDate()) {
                break;
            }
        }
        //取出最新資料的日期與今天日期
        $maxDate = $crawler->getMaxDate();
        $today = $crawler->getTodayInt();
        //判斷是否有取到最新日期
        if ($maxDate and $maxDate < $today) {
            //算出跟今天差了幾天
            $d1 = $crawler->DateIntToDateTime($maxDate);
            $d2 = $crawler->DateIntToDateTime($today);
            $diff = $d2->diff($d1);
            $c = (int) $diff->format('%d');
        } else {
            $c = 1;
        }
        $t = time();
    }

    //執行
    $crawler->run($t, $c);
    ?>
</pre>