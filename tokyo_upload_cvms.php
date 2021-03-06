<?php require_once('Connections/link.php');?>
<?php

$reUpload = 0;

$payment_files = getIsPayment();
// echo '<pre>';
// echo "Search status array size: ", var_dump($payment_files), "</br>";
// echo '</pre>';

$formatPaymentData = formatPaymentTxt($payment_files);
$uploadFileName = saveUploadTxtFile($formatPaymentData);
// $conn_id = connectFtpServer();
// uploadFileToFtp($conn_id, $uploadFileName);

echo 'END.'.'</br>';

function getIsPayment()
{
    // SQL 取出fee_order符合資料
    $searchStatusSqlCmd = "SELECT fee_order.account, fee_order.household_number, fee_order.begin_time, fee_order.virtual_account_id, fee_report_details.PayDate, fee_report_details.Chcode, fee_report_details.Fee, fee_report_details.DueDate, fee_report_details.ChannelCharge
                           FROM fee_order
                           INNER JOIN fee_report_details ON fee_report_details.CusCode LIKE CONCAT('%', fee_order.virtual_account_id, '%')
                           AND fee_order.status LIKE BINARY '%已繳費%'
                           AND fee_order.upload_time IS NULL";

    $searchStatusFromSql = mysql_query($searchStatusSqlCmd);
    if ($searchStatusFromSql) {
        $i = 0;
        while ($row= mysql_fetch_assoc($searchStatusFromSql)) {
            $payment_files[$i] = array(
                $row['account'],
                $row['household_number'],
                $row['begin_time'],
                $row['virtual_account_id'],   // 虛擬帳號
                $row['PayDate'],    // 繳費日期
                $row['Chcode'],   // 代收機構
                $row['Fee'],    // 代收金額
                $row['DueDate'],    // 預計入帳日
                $row['ChannelCharge']   // 通路手續費
            );

            $i++;
        }
    } else {
        echo "Search not found From fee_order.", "</br>";
    }

    return $payment_files;
}

function formatPaymentTxt($payment_files)
{
    if (count($payment_files) > 0) {
        for ($i = 0; $i < count($payment_files); $i++) {
            $success = 10;
            $error = 0;

            // 取資料
            $id = substr($payment_files[$i][0], 0, 5);
            $accountId = $payment_files[$i][1];
            $creatDate = $payment_files[$i][2];
            $virtualAccount = $payment_files[$i][3];
            $payDate = $payment_files[$i][4];
            $dueDate = $payment_files[$i][7];
            $collectionStore = $payment_files[$i][5];
            $collectionMoney = intval($payment_files[$i][6]);
            $fees = intval($payment_files[$i][8]) / 100;
            $inBankMoney = $collectionMoney + $fees;

            // 補位
            $id = str_pad($id, 5, " ", STR_PAD_LEFT);
            $accountId = str_pad($accountId, 10, " ", STR_PAD_LEFT);
            $creatDate = str_pad(convertDateToUploadFtpFormat($creatDate, 'Y-m'), 5, " ", STR_PAD_LEFT);
            $virtualAccount = str_pad($virtualAccount, 14, " ", STR_PAD_LEFT);
            $dueDate = str_pad(convertDateToUploadFtpFormat($dueDate), 7, " ", STR_PAD_LEFT);
            $payDate = str_pad(convertDateToUploadFtpFormat($payDate), 7, " ", STR_PAD_LEFT);
            $collectionStore = str_pad($collectionStore, 8, " ", STR_PAD_LEFT);
            $collectionMoney = str_pad($collectionMoney, 14, " ", STR_PAD_LEFT);
            $fees = str_pad($fees, 7, " ", STR_PAD_LEFT);
            $inBankMoney = str_pad($inBankMoney, 14, " ", STR_PAD_LEFT);

            // 格式檢查
            if (strlen($id) != 5) {
                echo 'payment_files['.$i.'] 1. id length error.', '</br>';
                $error++;
            }

            if (strlen($accountId) != 10) {
                echo 'payment_files['.$i.'] 2. accountId length error.'.'</br>';
                $error++;
            }

            if (strlen($creatDate) != 5) {
                echo 'payment_files['.$i.'] 3. create date length error.', '</br>';
                $error++;
            }

            if (strlen($virtualAccount) != 14) {
                echo 'payment_files['.$i.'] 4. virtual account length error.', '</br>';
                $error++;
            }

            if (strlen($payDate) != 7) {
                echo 'payment_files['.$i.'] 5. pay date length error.', '</br>';
                $error++;
            }

            if (strlen($dueDate) != 7) {
                echo 'payment_files['.$i.'] 6. in bank date length error.', '</br>';
                $error++;
            }

            if (strlen($collectionStore) != 8) {
                echo 'payment_files['.$i.'] 7. collection store length error.', '</br>';
                $error++;
            }

            if (strlen($collectionMoney) != 14) {
                echo 'payment_files['.$i.'] 8. collection money length error.', '</br>';
                $error++;
            }

            if (strlen($fees) != 7) {
                echo 'payment_files['.$i.'] 9. fees length error.', '</br>';
                $error++;
            }

            if (strlen($inBankMoney) != 14) {
                echo 'payment_files['.$i.'] 10. in bank money length error.', '</br>';
                $error++;
            }

            $formatPaymentData[$i] = $id.$accountId.$creatDate.$virtualAccount.$payDate.$dueDate.$collectionStore.$collectionMoney.$fees.$inBankMoneys;
            echo 'payment_files['.$i.'] Check Upload Format, Success: '. ($success - $error). ', Error: '. $error. '</br>';
        }

        return $formatPaymentData;
    }
}

function saveUploadTxtFile($formatPaymentData)
{
    if (count($formatPaymentData) > 0) {
        date_default_timezone_set("Asia/Taipei");
        $nowDate = date('Ymd');
        $fileName = 'ToCVMS-'.$nowDate.'.TXT';
        $myfile = fopen($fileName, "w") or die("Unable to open file!");
        for ($i = 0 ; $i < count($formatPaymentData); $i++) {
            fwrite($myfile, $formatPaymentData[$i].PHP_EOL);
        }
        fclose($myfile);

        return $fileName;
    }
}

function connectFtpServer()
{
    ### 連接的 FTP 伺服器是 localhost
    $conn_id = ftp_connect('ip');
    if ($conn_id == false) {
        echo "Connect ftp error.","</br>";
    }

    ### 登入 FTP, 帳號是 USERNAME, 密碼是 PASSWORD
    $login_result = ftp_login($conn_id, 'user', 'pass');
    if ($login_result == false) {
        echo "Ftp login error.","</br>";
    }

    //換目錄
    if (ftp_chdir($conn_id, "ftp-app2cvms")) {
        echo "Current directory is now: " . ftp_pwd($conn_id) . "</br>";
    } else {
        echo "Couldn't change directory.</br>";
    }

    return $conn_id;
}

function uploadFileToFtp($conn_id, $uploadFileName)
{
    if (count($uploadFileName) > 0) {
        $fp = fopen($uploadFileName, 'r');
        $logMessage = '';
        if (ftp_fput($conn_id, $uploadFileName, $fp, FTP_ASCII)) {
            echo "成功上傳 $file\n";
            $logMessage = 'upload success.';
            updateUploadTimeToDb();
        } else {
            echo "上傳檔案 $file 失敗\n";
            $logMessage = 'upload error.';

            if ($reUpload < 3) {
                sleep(900);
                uploadFileToFtp();
                $reUpload++;
            } else {
                $logMessage = 'Re Upload All Error.';
            }
        }
        saveLogTxtFile($logMessage);

        ftp_close($conn_id);
        fclose($fp);
    }
}

function saveLogTxtFile($message)
{
    date_default_timezone_set("Asia/Taipei");
    $nowDate = date('Y-m-d H:i:s');
    $fileName = 'TokyoCVMSLog.txt';
    $myfile = fopen($fileName, 'a') or die("Unable to open file!");
    $txt = $nowDate.'      '.$message.PHP_EOL;
    fwrite($myfile, $txt);
    fclose($myfile);
}

function convertDateToUploadFtpFormat($date, $format = 'Y-m-d', $addDayCount = 0)
{
    date_default_timezone_set("Asia/Taipei");
    $tempYear = date('Y', strtotime($date)) - 1911;
    $tempMonthAndDay = '';
    if ($format == 'Y-m-d') {
        $tempMonth = date('m', strtotime($date));
        $tempDay = date('d', strtotime($date)) + $addDayCount;
        $tempMonthAndDay = $tempMonth.str_pad($tempDay, 2, '0', STR_PAD_LEFT);
    }
    if ($format == 'Y-m') {
        $tempMonthAndDay = date('m', strtotime($date));
    }
    $convertDate = $tempYear.$tempMonthAndDay;

    // echo 'convert date: '.$convertDate.'</br>';

    return $convertDate;
}

function getInBankDateFromPaymentWay($payDate, $paymentWay)
{
    date_default_timezone_set("Asia/Taipei");
    $weekday = date("w", $payDate);   // Sunday = 0, Saturday = 6
    $weekdayCount = 0;
    // 判斷是否在假日
    if ($weekday == 0) {
        $weekdayCount = 1;
    }
    if ($weekday == 6) {
        $weekdayCount = 2;
    }

    switch ($paymentWay) {
      case 0:
        // 信用卡
        $inBankDate = convertDateToUploadFtpFormat($payDate, 'Y-m-d', 1 + $weekdayCount);
        break;
      case 1:
        // 臨櫃匯款
        $inBankDate = convertDateToUploadFtpFormat($payDate, 'Y-m-d', 1 + $weekdayCount);
        break;
      case 2:
        // ATM轉帳
        $inBankDate = convertDateToUploadFtpFormat($payDate, 'Y-m-d', 1 + $weekdayCount);
        break;
      case 3:
        // 超商代收
        $inBankDate = convertDateToUploadFtpFormat($payDate, 'Y-m-d', 3 + $weekdayCount);
        break;
      default:
        $inBankDate = '00000000';
        // 沒繳錢
        break;
    }

    return $inBankDate;
}

function updateUploadTimeToDb()
{
    date_default_timezone_set("Asia/Taipei");
    $upload_time = date('Y-m-d H:i:s');

    $updateUploadTimeSqlCmd = "UPDATE fee_order
                               SET upload_time = '$upload_time'
                               WHERE status LIKE BINARY '%已繳費%'
                               AND upload_time IS NULL";

    $updateUploadTimeToSql = mysql_query($updateUploadTimeSqlCmd);
    if ($updateUploadTimeToSql) {
        echo 'update upload_time to fee_order Success.'.'</br>';
    } else {
        echo 'update upload_time to fee_order Error.'. '</br>';
    }
}

?>
