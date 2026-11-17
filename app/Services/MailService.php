<?php

namespace App\Services;

use App\Mail\DynamicMail;
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

    /**
     * Dictionnaire anglais des e-mails identique à Next.js i18n-en.ts
     */
    protected static array $enDict = [
        "{code} est votre code de connexion {name}" => "{code} is your {name} sign-in code",
        "Votre code de connexion" => "Your sign-in code",
        "Code : {code}" => "Code: {code}",
        "Il est valable 10 minutes et ne peut servir qu'une fois." => "Valid for 10 minutes and single-use.",
        "Si vous n'avez pas demandé ce code, ignorez cet e-mail : personne ne peut se connecter sans lui." => "If you didn't ask for this code, ignore this email: no one can sign in without it.",
        "Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe." => "This email is sent to you by {name}. Never share your codes or passwords.",
        "Paiement reçu — commande {reference}" => "Payment received — order {reference}",
        "Nous avons bien reçu votre paiement" => "We received your payment",
        "Commande {reference} — {product} × {quantity} — {total}" => "Order {reference} — {product} × {quantity} — {total}",
        "Nous préparons vos accès. Vous recevrez un e-mail dès qu'ils sont disponibles." => "We're preparing your access. You'll get an email as soon as it's ready.",
        "Suivre ma commande" => "Track my order",
        "Votre accès est disponible — commande {reference}" => "Your access is ready — order {reference}",
        "Votre accès est disponible" => "Your access is ready",
        "Pour votre sécurité, les accès ne sont pas envoyés par e-mail : ils vous attendent dans votre espace « Mes achats »." => "For security, access details are not sent by email: they are waiting for you in “My orders”.",
        "Voir mes accès" => "View my access",
        "Activation en cours — commande {reference}" => "Activation in progress — order {reference}",
        "Votre activation est en cours" => "Your activation is in progress",
        "Cette offre est activée manuellement sur l'adresse e-mail que vous avez indiquée. Vous recevrez un e-mail dès que c'est fait." => "This offer is activated manually on the email address you gave. You'll get an email as soon as it's done.",
        "Votre commande {reference} est prise en charge" => "Your order {reference} is being handled",
        "Votre commande demande une intervention de notre équipe" => "Your order requires manual review by our team",
        "Votre paiement est bien enregistré, mais la livraison automatique n'a pas abouti. Notre équipe s'en occupe : vous serez livré ou remboursé." => "Your payment is recorded, but automatic delivery did not succeed. Our team is on it: you will be delivered or refunded.",
        "Inutile de payer à nouveau." => "No need to pay again.",
        "Commande {reference} remboursée" => "Order {reference} refunded",
        "Votre commande a été remboursée" => "Your order has been refunded",
        "Le remboursement a été émis vers votre moyen de paiement. Le délai d'arrivée dépend de votre opérateur." => "The refund was issued to your payment method. Arrival time depends on your operator.",
        "Voir ma commande" => "View my order",
        "Commande {reference} : avoir crédité" => "Order {reference}: credit applied",
        "Le montant de votre commande est crédité sur votre solde" => "The order amount has been credited to your balance",
        "{amount} sont disponibles sur votre solde. Vous pouvez les utiliser pour payer une prochaine commande." => "{amount} are available on your balance. You can use them to pay for your next purchase.",
        "Voir mon solde" => "View my balance",
        "{name} est de nouveau disponible" => "{name} is back in stock",
        "L'offre que vous attendiez est de retour" => "The offer you were waiting for is back",
        "{name} est de nouveau en stock." => "{name} is back in stock.",
        "Vous ne recevrez pas d'autre e-mail pour cette alerte." => "You won't receive another email for this alert.",
        "Voir l'offre" => "View offer",
        "Votre paiement n'est pas terminé — {product}" => "Your payment is not complete — {product}",
        "Votre paiement n'est pas encore confirmé" => "Your payment is not yet confirmed",
        "Si vous avez déjà payé, patientez quelques minutes : la confirmation peut prendre un peu de temps et ne demande aucune action. Ne payez pas une seconde fois." => "If you already paid, wait a few minutes: confirmation can take a short time and requires no action. Do not pay twice.",
        "Sinon, vous pouvez reprendre votre paiement tant que la session est ouverte (environ une heure après la commande)." => "Otherwise, you can resume your payment as long as the session is open (around one hour after ordering).",
        "Reprendre mon paiement" => "Resume my payment",
        "{product} vous attend toujours" => "{product} is still waiting for you",
        "Votre commande n'a pas été finalisée" => "Your order was not completed",
        "La session de paiement a expiré et aucun montant n'a été débité. L'offre est toujours disponible : vous pouvez recommencer en quelques instants." => "The payment session expired and no money was charged. The offer is still available: you can try again in seconds.",
        "Recommencer ma commande" => "Start my order again",
        "Vous recevez ce rappel parce que vous avez commencé une commande sur {name}. Pour ne plus en recevoir, désactivez-les dans votre Profil : {url}" => "You receive this reminder because you started an order on {name}. To stop receiving them, turn them off in your Profile: {url}",
        "Réponse à votre demande {reference}" => "Reply to your request {reference}",
        "Nous avons répondu à votre demande" => "We replied to your request",
        "Demande {reference} — {subject}" => "Request {reference} — {subject}",
        "Connectez-vous pour lire la réponse et y répondre." => "Sign in to read the reply and respond.",
        "Lire la réponse" => "Read the reply",
    ];

    public function __construct()
    {
        $this->siteName = Setting::get('site_name', 'Licences & Logiciels');
    }

    /**
     * Helper de traduction avec variables
     */
    protected function t(string $key, array $vars = [], string $lang = 'fr'): string
    {
        $out = $key;
        if ($lang === 'en' && isset(self::$enDict[$key])) {
            $out = self::$enDict[$key];
        }
        foreach ($vars as $k => $v) {
            $out = str_replace('{' . $k . '}', (string) $v, $out);
        }
        return $out;
    }

    /**
     * Formatage prix XOF
     */
    protected function formatXof(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Gabarit HTML / Texte identique à licences/src/server/mail/templates.ts
     */
    protected function renderTemplate(string $title, array $lines, ?array $cta = null, ?string $footer = null): array
    {
        $site = htmlspecialchars($this->siteName, ENT_QUOTES, 'UTF-8');
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedFooter = htmlspecialchars($footer ?? '', ENT_QUOTES, 'UTF-8');

        $htmlLines = '';
        $textLines = [];
        foreach ($lines as $line) {
            $textLines[] = $line;
            $htmlLines .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.55">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
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
<!doctype html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#0b1220">
<div style="max-width:520px;margin:0 auto;padding:24px 16px">
<div style="background:#ffffff;border-radius:16px;padding:28px 24px;border:1px solid #e6e9f1">
<p style="margin:0 0 20px;font-size:16px;font-weight:bold;color:#0d5c4a">{$site}</p>
<h1 style="margin:0 0 16px;font-size:20px;line-height:1.3">{$escapedTitle}</h1>
{$htmlLines}
{$ctaHtml}
</div>
<p style="margin:16px 4px 0;font-size:12px;line-height:1.5;color:#5b6475">{$escapedFooter}</p>
</div></body></html>
HTML;

        $text = $title . "\n\n" . implode("\n", $textLines) . $ctaText . "\n\n" . ($footer ?? '');

        return ['html' => $html, 'text' => $text];
    }

    /**
     * Envoi brut sécurisé (HTML + Texte)
     */
    protected function send(string $to, string $subject, array $rendered): bool
    {
        try {
            Mail::to($to)->send(new DynamicMail($subject, $rendered['html'], $rendered['text']));
            return true;
        } catch (\Throwable $e) {
            Log::error("[MailService] Échec d'envoi à {$to} ({$subject}) : " . $e->getMessage());
            return false;
        }
    }

    /**
     * 1. Code de connexion magique client (6 chiffres)
     */
    public function sendLoginCode(string $to, string $code, string $lang = 'fr'): bool
    {
        $subject = $this->t("{code} est votre code de connexion {name}", ['code' => $code, 'name' => $this->siteName], $lang);
        $footer = $this->t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", ['name' => $this->siteName], $lang);

        $rendered = $this->renderTemplate(
            $this->t('Votre code de connexion', [], $lang),
            [
                $this->t('Code : {code}', ['code' => $code], $lang),
                $this->t("Il est valable 10 minutes et ne peut servir qu'une fois.", [], $lang),
                $this->t("Si vous n'avez pas demandé ce code, ignorez cet e-mail : personne ne peut se connecter sans lui.", [], $lang),
            ],
            null,
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * Code 2FA Administrateur (6 chiffres, toujours en français)
     */
    public function sendAdmin2faCode(string $to, string $code): bool
    {
        $subject = "{$code} est votre code de vérification administrateur {$this->siteName}";
        $footer = "Cet e-mail vous est envoyé par {$this->siteName}. Ne transmettez jamais vos codes ni vos mots de passe.";

        $rendered = $this->renderTemplate(
            'Vérification de connexion administrateur',
            [
                "Code : {$code}",
                "Il est valable 10 minutes et ne peut servir qu'une fois.",
                "Si vous n'êtes pas à l'origine de cette connexion, ne communiquez ce code à personne et changez votre mot de passe sans attendre.",
            ],
            null,
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 2. Confirmation de paiement reçu
     */
    public function sendOrderPaid(Order $order, string $lang = 'fr'): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = $this->t("Paiement reçu — commande {reference}", ['reference' => $order->reference], $lang);
        $orderLine = $this->t("Commande {reference} — {product} × {quantity} — {total}", [
            'reference' => $order->reference,
            'product' => $order->product_name,
            'quantity' => $order->quantity,
            'total' => $this->formatXof($order->total_xof),
        ], $lang);
        $footer = $this->t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", ['name' => $this->siteName], $lang);

        $rendered = $this->renderTemplate(
            $this->t('Nous avons bien reçu votre paiement', [], $lang),
            [
                $orderLine,
                $this->t("Nous préparons vos accès. Vous recevrez un e-mail dès qu'ils sont disponibles.", [], $lang),
            ],
            [
                'label' => $this->t('Suivre ma commande', [], $lang),
                'url' => url("/compte/achats/{$order->id}"),
            ],
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 3. Accès disponibles / Commande livrée
     */
    public function sendOrderDelivered(Order $order, string $lang = 'fr'): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = $this->t("Votre accès est disponible — commande {reference}", ['reference' => $order->reference], $lang);
        $orderLine = $this->t("Commande {reference} — {product} × {quantity} — {total}", [
            'reference' => $order->reference,
            'product' => $order->product_name,
            'quantity' => $order->quantity,
            'total' => $this->formatXof($order->total_xof),
        ], $lang);
        $footer = $this->t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", ['name' => $this->siteName], $lang);

        $rendered = $this->renderTemplate(
            $this->t('Votre accès est disponible', [], $lang),
            [
                $orderLine,
                $this->t("Pour votre sécurité, les accès ne sont pas envoyés par e-mail : ils vous attendent dans votre espace « Mes achats ».", [], $lang),
            ],
            [
                'label' => $this->t('Voir mes accès', [], $lang),
                'url' => url("/compte/achats/{$order->id}"),
            ],
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 4. Activation manuelle en cours
     */
    public function sendOrderActivation(Order $order, string $lang = 'fr'): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = $this->t("Activation en cours — commande {reference}", ['reference' => $order->reference], $lang);
        $orderLine = $this->t("Commande {reference} — {product} × {quantity} — {total}", [
            'reference' => $order->reference,
            'product' => $order->product_name,
            'quantity' => $order->quantity,
            'total' => $this->formatXof($order->total_xof),
        ], $lang);
        $footer = $this->t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", ['name' => $this->siteName], $lang);

        $rendered = $this->renderTemplate(
            $this->t('Votre activation est en cours', [], $lang),
            [
                $orderLine,
                $this->t("Cette offre est activée manuellement sur l'adresse e-mail que vous avez indiquée. Vous recevrez un e-mail dès que c'est fait.", [], $lang),
            ],
            [
                'label' => $this->t('Suivre ma commande', [], $lang),
                'url' => url("/compte/achats/{$order->id}"),
            ],
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 5. Commande retardée
     */
    public function sendOrderDelayed(Order $order, string $lang = 'fr'): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = $this->t("Votre commande {reference} est prise en charge", ['reference' => $order->reference], $lang);
        $orderLine = $this->t("Commande {reference} — {product} × {quantity} — {total}", [
            'reference' => $order->reference,
            'product' => $order->product_name,
            'quantity' => $order->quantity,
            'total' => $this->formatXof($order->total_xof),
        ], $lang);
        $footer = $this->t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", ['name' => $this->siteName], $lang);

        $rendered = $this->renderTemplate(
            $this->t('Votre commande demande une intervention de notre équipe', [], $lang),
            [
                $orderLine,
                $this->t("Votre paiement est bien enregistré, mais la livraison automatique n'a pas abouti. Notre équipe s'en occupe : vous serez livré ou remboursé.", [], $lang),
                $this->t("Inutile de payer à nouveau.", [], $lang),
            ],
            [
                'label' => $this->t('Suivre ma commande', [], $lang),
                'url' => url("/compte/achats/{$order->id}"),
            ],
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 6. Commande remboursée
     */
    public function sendOrderRefunded(Order $order, string $lang = 'fr'): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = $this->t("Commande {reference} remboursée", ['reference' => $order->reference], $lang);
        $orderLine = $this->t("Commande {reference} — {product} × {quantity} — {total}", [
            'reference' => $order->reference,
            'product' => $order->product_name,
            'quantity' => $order->quantity,
            'total' => $this->formatXof($order->total_xof),
        ], $lang);
        $footer = $this->t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", ['name' => $this->siteName], $lang);

        $rendered = $this->renderTemplate(
            $this->t('Votre commande a été remboursée', [], $lang),
            [
                $orderLine,
                $this->t("Le remboursement a été émis vers votre moyen de paiement. Le délai d'arrivée dépend de votre opérateur.", [], $lang),
            ],
            [
                'label' => $this->t('Voir ma commande', [], $lang),
                'url' => url("/compte/achats/{$order->id}"),
            ],
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 6b. Commande créditée en avoir
     */
    public function sendOrderCredited(Order $order, string $lang = 'fr'): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $subject = $this->t("Commande {reference} : avoir crédité", ['reference' => $order->reference], $lang);
        $orderLine = $this->t("Commande {reference} — {product} × {quantity} — {total}", [
            'reference' => $order->reference,
            'product' => $order->product_name,
            'quantity' => $order->quantity,
            'total' => $this->formatXof($order->total_xof),
        ], $lang);
        $footer = $this->t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", ['name' => $this->siteName], $lang);

        $rendered = $this->renderTemplate(
            $this->t('Le montant de votre commande est crédité sur votre solde', [], $lang),
            [
                $orderLine,
                $this->t("{amount} sont disponibles sur votre solde. Vous pouvez les utiliser pour payer une prochaine commande.", ['amount' => $this->formatXof($order->total_xof)], $lang),
            ],
            [
                'label' => $this->t('Voir mon solde', [], $lang),
                'url' => url("/compte/solde"),
            ],
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 7. Alerte de retour en stock
     */
    public function sendStockAlert(string $to, Product $product, string $lang = 'fr'): bool
    {
        $subject = $this->t("{name} est de nouveau disponible", ['name' => $product->name], $lang);
        $footer = $this->t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", ['name' => $this->siteName], $lang);

        $rendered = $this->renderTemplate(
            $this->t("L'offre que vous attendiez est de retour", [], $lang),
            [
                $this->t("{name} est de nouveau en stock.", ['name' => $product->name], $lang),
                $this->t("Vous ne recevrez pas d'autre e-mail pour cette alerte.", [], $lang),
            ],
            [
                'label' => $this->t("Voir l'offre", [], $lang),
                'url' => url("/boutique/{$product->slug}"),
            ],
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 8. Relance de paiement non terminé (Étape 1 ou 2)
     */
    public function sendPaymentReminder(Order $order, int $stage, string $slug, string $lang = 'fr'): bool
    {
        $to = $order->customer_email ?: $order->user?->email;
        if (!$to) return false;

        $footer = $this->t(
            "Vous recevez ce rappel parce que vous avez commencé une commande sur {name}. Pour ne plus en recevoir, désactivez-les dans votre Profil : {url}",
            ['name' => $this->siteName, 'url' => url("/compte/profil")],
            $lang
        );

        $orderLine = $this->t("Commande {reference} — {product} × {quantity} — {total}", [
            'reference' => $order->reference,
            'product' => $order->product_name,
            'quantity' => $order->quantity,
            'total' => $this->formatXof($order->total_xof),
        ], $lang);

        if ($stage === 1) {
            $subject = $this->t("Votre paiement n'est pas terminé — {product}", ['product' => $order->product_name], $lang);
            $rendered = $this->renderTemplate(
                $this->t("Votre paiement n'est pas encore confirmé", [], $lang),
                [
                    $orderLine,
                    $this->t("Si vous avez déjà payé, patientez quelques minutes : la confirmation peut prendre un peu de temps et ne demande aucune action. Ne payez pas une seconde fois.", [], $lang),
                    $this->t("Sinon, vous pouvez reprendre votre paiement tant que la session est ouverte (environ une heure après la commande).", [], $lang),
                ],
                [
                    'label' => $this->t("Reprendre mon paiement", [], $lang),
                    'url' => url("/compte/achats/{$order->id}"),
                ],
                $footer
            );
        } else {
            $subject = $this->t("{product} vous attend toujours", ['product' => $order->product_name], $lang);
            $rendered = $this->renderTemplate(
                $this->t("Votre commande n'a pas été finalisée", [], $lang),
                [
                    $orderLine,
                    $this->t("La session de paiement a expiré et aucun montant n'a été débité. L'offre est toujours disponible : vous pouvez recommencer en quelques instants.", [], $lang),
                ],
                [
                    'label' => $this->t("Recommencer ma commande", [], $lang),
                    'url' => url("/commande/validation?produit=" . urlencode($slug)),
                ],
                $footer
            );
        }

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 9. Réponse à un ticket d'assistance
     */
    public function sendTicketReply(string $to, Ticket $ticket, TicketMessage $message, string $lang = 'fr'): bool
    {
        $subject = $this->t("Réponse à votre demande {reference}", ['reference' => $ticket->reference], $lang);
        $footer = $this->t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", ['name' => $this->siteName], $lang);

        $rendered = $this->renderTemplate(
            $this->t("Nous avons répondu à votre demande", [], $lang),
            [
                $this->t("Demande {reference} — {subject}", ['reference' => $ticket->reference, 'subject' => $ticket->subject], $lang),
                $this->t("Connectez-vous pour lire la réponse et y répondre.", [], $lang),
            ],
            [
                'label' => $this->t("Lire la réponse", [], $lang),
                'url' => url("/compte/assistance/{$ticket->id}"),
            ],
            $footer
        );

        return $this->send($to, $subject, $rendered);
    }

    /**
     * 10. Alerte administrateur interne (toujours en français)
     */
    public function sendAdminAlert(string $to, string $subject, array $lines, string $path = '/admin'): bool
    {
        $fullSubject = "[{$this->siteName}] {$subject}";
        $footer = "Alerte interne destinée aux administrateurs.";

        $rendered = $this->renderTemplate(
            $subject,
            $lines,
            [
                'label' => 'Ouvrir le backoffice',
                'url' => url($path),
            ],
            $footer
        );

        return $this->send($to, $fullSubject, $rendered);
    }
}
