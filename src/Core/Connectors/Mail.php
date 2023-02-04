<?php

namespace App\Core\Connectors;

use App\Application;
use SendGrid;
use SendGrid\Mail\TypeException;
use function App\Connectors\Core\str_contains;

class Mail extends SendGrid
{
    /**
     * @var string $fromEmail From email address.
     */
    private string $fromEmail;

    /**
     * @var string $fromName From name.
     */
    private string $fromName;

    /**
     * Constructor.
     */
    public function __construct ()
    {
        parent::__construct(Application::get('env:SENDGRID_API_KEY'));
        [ $this->fromEmail, $this->fromName ] = $this->parseMailString(Application::get('env:SENDGRID_FROM'));
    }

    /**
     * Parse a mail string format like "John Doe <username@domain.com>"
     *
     * @param string $mail
     * @return array
     */
    private function parseMailString (string $mail): array
    {
        if (str_contains($mail, '<') === false) {
            return [$mail, $mail];
        }

        $parts = explode('<', $mail);
        $name = trim($parts[0]);
        $email = trim($parts[1], '>');
        return [$email, $name];
    }

    /**
     * Send an email.
     *
     * @throws TypeException
     */
    public function go (string $to, string $subject, string $body): SendGrid\Response
    {
        $email = new SendGrid\Mail\Mail();
        $email->setFrom($this->fromEmail, $this->fromName);
        $email->setSubject($subject);
        $email->addTo($to);
        $email->addContent('text/html', $body);
        return $this->send($email);
    }
}