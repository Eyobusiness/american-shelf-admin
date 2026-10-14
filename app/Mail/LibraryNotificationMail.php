<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LibraryNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $subjectText,
        public string $bodyContent,
        public ?string $recipientName = null,
        public ?string $typeBadge = null,
        public array $details = []
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml()
        );
    }

    protected function buildHtml(): string
    {
        $salutation = $this->recipientName
            ? '<p style="font-size: 15px; font-weight: 600; color: #0D2B45; margin: 0 0 16px 0;">Bonjour ' . htmlspecialchars($this->recipientName, ENT_QUOTES, 'UTF-8') . ',</p>'
            : '';

        $badgeHtml = '';
        if ($this->typeBadge) {
            $badgeBg = '#0D2B45';
            if (str_contains(strtoupper($this->typeBadge), 'RETARD')) {
                $badgeBg = '#C93B2B';
            } elseif (str_contains(strtoupper($this->typeBadge), 'CONFIRM') || str_contains(strtoupper($this->typeBadge), 'CREEE')) {
                $badgeBg = '#10B981';
            }
            $badgeHtml = '<div style="margin-bottom: 16px;"><span style="display: inline-block; background-color: ' . $badgeBg . '; color: #ffffff; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 9999px;">' . htmlspecialchars($this->typeBadge, ENT_QUOTES, 'UTF-8') . '</span></div>';
        }

        $detailsHtml = '';
        if (!empty($this->details)) {
            $detailsRows = '';
            foreach ($this->details as $label => $value) {
                if ($value !== null && $value !== '') {
                    $detailsRows .= '<tr>' .
                        '<td style="padding: 8px 12px; font-size: 13px; color: #64748B; font-weight: 600; width: 35%; border-bottom: 1px solid #F1F5F9;">' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</td>' .
                        '<td style="padding: 8px 12px; font-size: 13px; color: #1E293B; font-weight: 600; border-bottom: 1px solid #F1F5F9;">' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>' .
                        '</tr>';
                }
            }

            if ($detailsRows !== '') {
                $detailsHtml = '<div style="margin: 20px 0; background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">' .
                    '<table style="width: 100%; border-collapse: collapse; text-align: left;">' .
                    $detailsRows .
                    '</table>' .
                    '</div>';
            }
        }

        $formattedBody = nl2br(htmlspecialchars($this->bodyContent, ENT_QUOTES, 'UTF-8'));
        $subject = htmlspecialchars($this->subjectText, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>' .
'<html lang="fr">' .
'<head>' .
'    <meta charset="UTF-8">' .
'    <meta name="viewport" content="width=device-width, initial-scale=1.0">' .
'    <title>' . $subject . '</title>' .
'</head>' .
'<body style="margin: 0; padding: 24px 12px; background-color: #F1F5F9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1E293B;">' .
'    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' .
'        <tr>' .
'            <td align="center">' .
'                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background-color: #FFFFFF; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #E2E8F0;">' .
'                    <tr>' .
'                        <td style="background: linear-gradient(135deg, #0D2B45 0%, #16425B 100%); padding: 24px 32px; text-align: left; border-bottom: 4px solid #C93B2B;">' .
'                            <div style="font-size: 20px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.5px; text-transform: uppercase;">' .
'                                American Shelf Abobo' .
'                            </div>' .
'                            <div style="font-size: 12px; color: #94A3B8; margin-top: 4px; font-weight: 500;">' .
'                                Espace Culturel &amp; Bibliothèque de Référence' .
'                            </div>' .
'                        </td>' .
'                    </tr>' .
'                    <tr>' .
'                        <td style="padding: 32px 32px 24px 32px;">' .
                            $badgeHtml .
                            $salutation .
'                            <div style="font-size: 14px; line-height: 1.7; color: #334155;">' .
                                $formattedBody .
'                            </div>' .
                            $detailsHtml .
'                            <div style="margin-top: 28px; padding: 14px 16px; background-color: #EFF6FF; border-left: 4px solid #3B82F6; border-radius: 4px; font-size: 12px; line-height: 1.6; color: #1E40AF;">' .
'                                <strong>Instructions utiles :</strong> Présentez-vous à l\'accueil de la bibliothèque muni(e) de votre carte d\'adhérent pour toute opération de retrait ou de restitution.' .
'                            </div>' .
'                        </td>' .
'                    </tr>' .
'                    <tr>' .
'                        <td style="background-color: #F8FAFC; padding: 20px 32px; border-top: 1px solid #E2E8F0; text-align: center;">' .
'                            <p style="font-size: 11px; color: #64748B; margin: 0 0 6px 0; line-height: 1.5;">' .
'                                <strong>American Shelf Abobo</strong> &bull; Abidjan, Côte d\'Ivoire &bull; Tous droits réservés' .
'                            </p>' .
'                            <p style="font-size: 11px; color: #94A3B8; margin: 0; line-height: 1.4;">' .
'                                Ce message est généré automatiquement par notre système documentaire. Merci de ne pas y répondre directement.' .
'                            </p>' .
'                        </td>' .
'                    </tr>' .
'                </table>' .
'            </td>' .
'        </tr>' .
'    </table>' .
'</body>' .
'</html>';
    }
}