<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    protected string $siteName;

    public function __construct()
    {
        $this->siteName = Setting::get('site_name', 'Licences & Logiciels');
    }

    /**
     * Construit le gabarit HTML / Texte identique à licences/src/server/mail/templates.ts
     */
    protected function renderTemplate(string $title, array $lines, ?array $cta = null, ?string $footer = null): array
    {
        $site = htmlspecialchars($this->siteName, ENT_QUOTES, 'UTF-8');
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $footerText = $footer ?: "Cet e-mail vous est envoyé par {$this->siteName}. Ne transmettez jamais vos codes ni vos mots de passe.";
        $escapedFooter = htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8');

        $htmlLines = '';
        $textLines = [];
        foreach ($lines as $line) {
            $textLines[] = $line;
            $htmlLines .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.55;color:#0b1220">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $ctaHtml = '';
        $ctaText = '';
        if ($cta && !empty($cta['url']) && !empty($cta['label'])) {
            $ctaUrl = htmlspecialchars($cta['url'], ENT_QUOTES, 'UTF-8');
            $ctaLabel = htmlspecialchars($cta['label'], ENT_QUOTES, 'UTF-8');
            $ctaHtml = '<p style="margin:24px 0 4px"><a href="' . $ctaUrl . '" style="display:inline-block;background:#ff6a13;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:12px;font-size:15px;font-weight:bold">' . $ctaLabel . '</a></p>';
            $ctaText = "\n" . $cta['label'] . ' : ' . $cta['url'];
        }

        $html = <<<HTML
<!doctype html>
<html>
<body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#0b1220">
  <div style="max-width:520px;margin:0 auto;padding:24px 16px">
    <div style="background:#ffffff;border-radius:16px;padding:28px 24px;border:1px solid #e6e9f1">
      <p style="margin:0 0 20px;font-size:16px;font-weight:bold;color:#0d5c4a">{$site}</p>
      <h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;color:#0b1220">{$escapedTitle}</h1>
      {$htmlLines}
      {$ctaHtml}
    </div>
    <p style="margin:16px 4px 0;font-size:12px;line-height:1.5;color:#5b6475">{$escapedFooter}</p>
  </div>
</body>
</html>
HTML;

        $text = $title . "\n\n" . implode("\n", $textLines) . $ctaText . "\n\n" . $footerText;

        return ['html' => $html, 'text' => $text];
    }

    /**
     * Envoi brut sécurisé (HTML + Texte alternatif)
     */
    protected function send(string $to, string $subject, array $rendered): bool
    {
        try {
            Mail::send([], [], function ($message) use ($to, $subject, $rendered) {
                $message->to($to)
                    ->subject($subject)
                    ->html($rendered['html'])
                    ->text($rendered['text']);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error("[MailService] Échec d'envoi à {$to} ({$subject}) : " . $e->getMessage());
            return false;
        }
    }

    /**
     * 1. Code de connexion magique client (6 chiffres)
     */
    public function sendLoginCode(string $to, string $code): bool
    {
        $subject = "{$code} est votre code de connexion {$this->siteName}";
        $rendered = $this->renderTemplate(
            'Votre code de connexion',
            [
                "Code : {$code}",
                "Il est valable 15 minutes et ne peut servir qu'une fois.",
                "Si vous n'avez pas demandé ce code, ignorez cet e-mail : personne ne peut se connecter sans lui.",
            ]
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 2. Confirmation de paiement reçu
     */
    public function sendOrderPaid(Order $order): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = "Paiement reçu — commande {$order->reference}";
        $formattedPrice = number_format($order->total_xof, 0, ',', ' ') . ' FCFA';
        $orderUrl = url("/compte/achats/{$order->id}");

        $rendered = $this->renderTemplate(
            'Nous avons bien reçu votre paiement',
            [
                "Commande {$order->reference} — {$order->product_name} × {$order->quantity} — {$formattedPrice}",
                "Nous préparons vos accès. Vous recevrez un e-mail dès qu'ils sont disponibles.",
            ],
            [
                'label' => 'Suivre ma commande',
                'url' => $orderUrl,
            ]
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 3. Accès disponibles / Commande livrée
     */
    public function sendOrderDelivered(Order $order): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = "Votre accès est disponible — commande {$order->reference}";
        $formattedPrice = number_format($order->total_xof, 0, ',', ' ') . ' FCFA';
        $orderUrl = url("/compte/achats/{$order->id}");

        $rendered = $this->renderTemplate(
            'Votre accès est disponible',
            [
                "Commande {$order->reference} — {$order->product_name} × {$order->quantity} — {$formattedPrice}",
                "Pour votre sécurité, les accès ne sont pas envoyés par e-mail : ils vous attendent dans votre espace « Mes achats ».",
            ],
            [
                'label' => 'Voir mes accès',
                'url' => $orderUrl,
            ]
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 4. Activation manuelle en cours
     */
    public function sendOrderActivation(Order $order): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = "Activation en cours — commande {$order->reference}";
        $formattedPrice = number_format($order->total_xof, 0, ',', ' ') . ' FCFA';
        $orderUrl = url("/compte/achats/{$order->id}");

        $rendered = $this->renderTemplate(
            'Votre activation est en cours',
            [
                "Commande {$order->reference} — {$order->product_name} × {$order->quantity} — {$formattedPrice}",
                "Cette offre est activée manuellement sur l'adresse e-mail que vous avez indiquée. Vous recevrez un e-mail dès que c'est fait.",
            ],
            [
                'label' => 'Suivre ma commande',
                'url' => $orderUrl,
            ]
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 5. Commande retardée / Problème fournisseur
     */
    public function sendOrderDelayed(Order $order): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = "Votre commande {$order->reference} est prise en charge";
        $formattedPrice = number_format($order->total_xof, 0, ',', ' ') . ' FCFA';
        $orderUrl = url("/compte/achats/{$order->id}");

        $rendered = $this->renderTemplate(
            'Votre commande demande une intervention de notre équipe',
            [
                "Commande {$order->reference} — {$order->product_name} × {$order->quantity} — {$formattedPrice}",
                "Votre paiement est bien enregistré, mais la livraison automatique a rencontré un contretemps. Notre équipe s'en occupe : vous serez livré ou remboursé sous peu.",
                "Inutile de payer à nouveau.",
            ],
            [
                'label' => 'Suivre ma commande',
                'url' => $orderUrl,
            ]
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 6. Commande remboursée
     */
    public function sendOrderRefunded(Order $order): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = "Commande {$order->reference} remboursée";
        $formattedPrice = number_format($order->total_xof, 0, ',', ' ') . ' FCFA';
        $orderUrl = url("/compte/achats/{$order->id}");

        $rendered = $this->renderTemplate(
            'Votre commande a été remboursée',
            [
                "Commande {$order->reference} — {$order->product_name} × {$order->quantity} — {$formattedPrice}",
                "Le remboursement a été validé. Si le remboursement a été fait sur votre solde, vos fonds sont disponibles immédiatement sur votre compte client. S'il a été émis sur votre Mobile Money, les délais dépendent de l'opérateur.",
            ],
            [
                'label' => 'Voir ma commande',
                'url' => $orderUrl,
            ]
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 7. Alerte de retour en stock
     */
    public function sendStockAlert(string $to, Product $product): bool
    {
        $subject = "{$product->name} est de nouveau disponible !";
        $url = url("/boutique/{$product->slug}");

        $rendered = $this->renderTemplate(
            'Bonne nouvelle : produit de nouveau en stock',
            [
                "Le produit « {$product->name} » pour lequel vous aviez demandé une alerte est de nouveau disponible à l'achat.",
                "Les stocks peuvent être limités, nous vous invitons à finaliser votre commande rapidement.",
            ],
            [
                'label' => 'Voir le produit',
                'url' => $url,
            ]
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 8. Réponse à un ticket d'assistance
     */
    public function sendTicketReply(string $to, Ticket $ticket, TicketMessage $message): bool
    {
        $subject = "Nouveau message — Ticket {$ticket->reference} : {$ticket->subject}";
        $url = url("/compte/tickets/{$ticket->id}");

        $rendered = $this->renderTemplate(
            'Notre équipe vous a répondu',
            [
                "Ticket {$ticket->reference} : {$ticket->subject}",
                "Extrait de la réponse :",
                "« " . substr(strip_tags($message->body), 0, 250) . "... »",
            ],
            [
                'label' => 'Consulter le ticket',
                'url' => $url,
            ]
        );

        return $this->send($to, $subject, $rendered);
    }
}
