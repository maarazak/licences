/** Logos des moyens de paiement acceptés (fichiers locaux dans public/payments), partagés par l'achat et la recharge. */
export interface PayLogo {
  src: string;
  label: string;
}

export const MOBILE_LOGOS: PayLogo[] = [
  { src: '/payments/orange-money.png', label: 'Orange Money' },
  { src: '/payments/moov-money.png', label: 'Moov Money' },
  { src: '/payments/wave.png', label: 'Wave' },
  { src: '/payments/visa.svg', label: 'Visa' },
  { src: '/payments/mastercard.svg', label: 'Mastercard' },
];

export const CRYPTO_LOGOS: PayLogo[] = [
  { src: '/payments/crypto/tether.svg', label: 'USDT' },
  { src: '/payments/crypto/bitcoin.svg', label: 'Bitcoin' },
  { src: '/payments/crypto/ethereum.svg', label: 'Ethereum' },
  { src: '/payments/crypto/solana.svg', label: 'Solana' },
  { src: '/payments/crypto/ton.svg', label: 'TON' },
  { src: '/payments/crypto/litecoin.svg', label: 'Litecoin' },
];

/** Logos empilés dans la tuile de gauche de chaque carte. */
export const MOBILE_ICON_LOGOS: PayLogo[] = [MOBILE_LOGOS[0], MOBILE_LOGOS[2], MOBILE_LOGOS[1]];
export const CRYPTO_ICON_LOGOS: PayLogo[] = CRYPTO_LOGOS.slice(0, 3);
