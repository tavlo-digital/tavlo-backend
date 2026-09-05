<?php

namespace App\Mail;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The FinanzOnline web-service user has to be created inside the restaurant's
 * own tax account, and most owners hand that to their accountant.
 */
class FinanzOnlineInstructionsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Vendor $vendor,
        public ?string $recipientName = null,
        public string $language = 'en',
    ) {}

    public function build(): self
    {
        $restaurant = e($this->vendor->restaurant_name ?: $this->vendor->name ?: 'the restaurant');
        $greeting = $this->recipientName ? 'Hello '.e($this->recipientName).',' : 'Hello,';

        return $this->language === 'de'
            ? $this->german($restaurant, $greeting)
            : $this->english($restaurant, $greeting);
    }

    private function english(string $restaurant, string $greeting): self
    {
        return $this
            ->subject("Set up the FinanzOnline cash register user for {$restaurant}")
            ->html(<<<HTML
                <p>{$greeting}</p>
                <p><strong>{$restaurant}</strong> is setting up Tavlo as its cash register and needs a
                FinanzOnline web-service user so the register can be reported to the tax office
                automatically.</p>
                <p>This is <em>not</em> the normal FinanzOnline login &mdash; it is a separate,
                restricted user created only for this purpose.</p>
                <ol>
                    <li>Log into the restaurant's own FinanzOnline account</li>
                    <li>Go to <strong>Admin &rarr; Benutzer Einzel</strong></li>
                    <li>Tick <em>"Ich m&ouml;chte einen neuen Benutzer hinzuf&uuml;gen"</em> and select <strong>Anfordern</strong></li>
                    <li>Under <strong>Benutzerkennung</strong>, set <em>"Benutzer f&uuml;r Registrierkassen-WebService"</em> to <strong>Ja</strong></li>
                    <li>Choose a PIN, then <strong>Weiter &rarr; Daten pr&uuml;fen &rarr; Speichern</strong></li>
                </ol>
                <p>Three values come out of this and are needed to finish setup:</p>
                <ul>
                    <li><strong>Teilnehmer-Identifikation (TID)</strong> &mdash; shown on the account</li>
                    <li><strong>Benutzer-ID</strong> &mdash; you choose it: 8&ndash;12 characters, at least one letter and one digit</li>
                    <li><strong>PIN</strong> &mdash; you choose it</li>
                </ul>
                <p><strong>Important:</strong> FinanzOnline shows the PIN only once, at creation.
                Write it down immediately &mdash; if it is lost, a new web-service user has to be
                created from scratch.</p>
                <p>Please send these three values back to {$restaurant}.</p>
            HTML);
    }

    private function german(string $restaurant, string $greeting): self
    {
        return $this
            ->subject("FinanzOnline Registrierkassen-Benutzer für {$restaurant} anlegen")
            ->html(<<<HTML
                <p>{$greeting}</p>
                <p><strong>{$restaurant}</strong> richtet Tavlo als Registrierkasse ein und ben&ouml;tigt
                einen FinanzOnline-Webservice-Benutzer, damit die Kasse automatisch beim Finanzamt
                registriert werden kann.</p>
                <p>Das ist <em>nicht</em> der normale FinanzOnline-Login, sondern ein eigener,
                eingeschr&auml;nkter Benutzer nur f&uuml;r diesen Zweck.</p>
                <ol>
                    <li>Im FinanzOnline-Konto des Restaurants anmelden</li>
                    <li><strong>Admin &rarr; Benutzer Einzel</strong> &ouml;ffnen</li>
                    <li><em>"Ich m&ouml;chte einen neuen Benutzer hinzuf&uuml;gen"</em> ankreuzen und <strong>Anfordern</strong> w&auml;hlen</li>
                    <li>Unter <strong>Benutzerkennung</strong> die Benutzerart <em>"Benutzer f&uuml;r Registrierkassen-WebService"</em> auf <strong>Ja</strong> setzen</li>
                    <li>PIN vergeben, dann <strong>Weiter &rarr; Daten pr&uuml;fen &rarr; Speichern</strong></li>
                </ol>
                <p>Daraus ergeben sich drei Werte:</p>
                <ul>
                    <li><strong>Teilnehmer-Identifikation (TID)</strong></li>
                    <li><strong>Benutzer-ID</strong> &mdash; 8&ndash;12 Zeichen, mindestens ein Buchstabe und eine Ziffer</li>
                    <li><strong>PIN</strong></li>
                </ul>
                <p><strong>Wichtig:</strong> Die PIN wird nur einmal beim Anlegen angezeigt. Bitte
                sofort notieren &mdash; sonst muss ein neuer Webservice-Benutzer angelegt werden.</p>
                <p>Bitte senden Sie diese drei Werte an {$restaurant} zur&uuml;ck.</p>
            HTML);
    }
}
