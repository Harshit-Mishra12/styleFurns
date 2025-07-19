<?php

namespace App\Helpers;

use File;
use Exception;
use Illuminate\Support\Facades\Mail;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;


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


    public static function saveImageToServer2($file, $dir)
    {
        $dir = trim($dir, '/'); // clean up the dir input
        $path = public_path($dir); // ✅ this automatically joins public/ with the given path

        if (!File::exists($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        $filename = rand(10000, 100000) . '_' . time() . '_' . $file->getClientOriginalName();
        $file->move($path, $filename);

        //  $baseUrl = config('app.url', 'http://localhost/'); // use config fallback
        $baseUrl = getenv('APP_URL');
        $filePath = $baseUrl . '/' . $dir . '/' . $filename;

        return $filePath;
    }
    public static function saveImageToServer($file, $dir)
    {
        // Normalize and clean up $dir (e.g., 'uploads/bookings/')
        $relativeDir = trim($dir, '/\\'); // removes leading/trailing slashes
        $path = public_path($relativeDir);

        // Ensure folder exists
        if (!File::exists($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        // Clean filename
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $originalName);

        $filename = rand(10000, 100000) . '_' . time() . '_' . $safeName . '.' . $extension;

        // Move file
        $file->move($path, $filename);

        // Get APP_URL correctly and ensure trailing slash is present
        $baseUrl = rtrim(env('APP_URL', config('app.url')), '/');



        // Build correct public URL
        $url = $baseUrl . '/public/' . $relativeDir . '/' . $filename;

        return $url;
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
    public static function sendPushNotification($notificationType, $technicianIds)
    {
        $title = '';
        $body = '';

        // Map notification type to title and body
        switch ($notificationType) {
            case 1:
                $title = 'New Booking Assigned';
                $body = 'A new booking has been assigned to you.';
                break;
            case 2:
                $title = 'Booking Rescheduled';
                $body = 'A booking has been rescheduled to you.';
                break;
            case 3:
                $title = 'Booking Unassigned';
                $body = 'One of your bookings has been rescheduled and is no longer assigned to you.';
                break;
            case 4:
                $title = 'Location Update Needed';
                $body = 'Please remember to keep updating your locations.';
                break;
            case 5:
                $title = 'Shift Starting Soon';
                $body = 'Your shift is about to start. Please remember to update online status.';
                break;
            case 6:
                $title = 'Shift Ending Soon';
                $body = 'Your shift is ending soon. Please remember to update offline status.';
                break;
            default:
                $title = 'Notification';
                $body = 'You have a new notification.';
        }

        foreach ($technicianIds as $techId) {
            // Fetch device tokens from user_push_tokens table
            $tokens = DB::table('user_push_tokens')
                ->where('user_id', $techId)
                ->pluck('device_token')
                ->toArray();

            if (empty($tokens)) {
                \Log::warning("No device tokens found for technician ID: $techId");
                continue;
            }

            foreach ($tokens as $token) {
                $message = [
                    'to'    => $token,
                    'sound' => 'default',
                    'title' => $title,
                    'body'  => $body,
                    'data'  => ['notificationType' => $notificationType, 'technicianId' => $techId],
                ];

                try {
                    Http::post("https://exp.host/--/api/v2/push/send", $message);
                } catch (\Exception $e) {
                    \Log::error("Failed to send notification to technician $techId: " . $e->getMessage());
                }
            }
        }
    }
}
