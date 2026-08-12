<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        $url = $this->resetUrl($notifiable);
        $expires = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Permintaan reset password akun SIPS Mobile Anda')
            ->replyTo('it.helpdesk@skj.co.id', 'IT Helpdesk SKJ')
            ->view('emails.reset-password', [
                'url' => $url,
                'user' => $notifiable,
                'expires' => $expires,
            ])
            ->text('emails.reset-password_text', [
                'url' => $url,
                'user' => $notifiable,
                'expires' => $expires,
            ]);
    }

    /**
     * Get the reset URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function resetUrl($notifiable)
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        $base = rtrim((string) (env('PASSWORD_RESET_URL') ?: config('app.url')), '/');

        return $base.'/reset-password'
            .'?email='.urlencode($notifiable->getEmailForPasswordReset())
            .'&token='.urlencode($this->token);
    }
}