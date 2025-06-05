<?php

namespace App\Helpers;

use File;
use Exception;
use Illuminate\Support\Facades\Mail;
use DateTime;
use DateTimeZone;

class Helper
{
    public static function getApiKey()
    {
        // return 'c87ea341-cb2a-499d-bc06-388253b04c6d';
        // return 'c817d386-adb5-41de-b355-5153316adc8f';
        return '5c308ca5-9f4a-4f3d-9831-f7fe11548395';
    }
    public static function getRazorpayKeyId()
    {
        return "rzp_test_f4vRcPjcfkxA5i";
    }
    public static function getRazorpayKeySecret()
    {
        return "1MPr7uqq8zXr9K95SnYwUDI5";
    }


    public static function saveImageToServer($file, $dir)
    {
        $path = public_path() . $dir;
        if (!File::exists($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        $filename = rand(10000, 100000) . '_' . time() . '_' . $file->getClientOriginalName();
        $file->move($path, $filename);

        // $baseUrl = getenv('APP_URL');
        // $baseUrl = config('app.base_url');
        $baseUrl = 'http://localhost/';
        // $baseUrl = 'https://paleturquoise-crab-208767.hostingersite.com/runskart/public';


        $filePath = $baseUrl . $dir . $filename;

        return $filePath;
    }
    public static function deleteImageFromServer($filePath)
    {
        if (File::exists(public_path($filePath))) {
            return File::delete(public_path($filePath));
        }

        return false;
    }
    public static function sendEmail($to, $subject, $body)
    {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

        $credentials = \SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', config('app.sendinblue_key'));

        $sendSmtpEmail = new \SendinBlue\Client\Model\SendSmtpEmail([
            'subject' => $subject,
            'sender' => ['name' => 'Attire', 'email' => 'hi@backlsh.com'],
            'replyTo' => ['name' => 'Backlsh', 'email' => 'hi@backlsh.com'],
            'to' => [['name' => 'Max Mustermann', 'email' => $to]],
            //    'htmlContent' => '<html><body><h1>This is a transactional email {{params.bodyMessage}}</h1></body></html>',
            'htmlContent' => $body,
            'params' => ['bodyMessage' => 'made just for you!']
        ]);

        try {
            //  $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
            echo $e->getMessage(), PHP_EOL;
        }
    }

    public static function sendPhpEmail($to, $subject, $body, $headers)
    {
        mail($to, $subject, $body, $headers);
    }
}
