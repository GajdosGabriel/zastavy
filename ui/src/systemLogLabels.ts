// Slovenské názvy udalostí denníka (kód `kanál.udalosť` zapisuje App\Services\SystemLog\Recorder).
const eventLabels: Record<string, string> = {
      'mail.sent': 'E-mail odoslaný',
      'mail.failed': 'E-mail zlyhal',
      'auth.login': 'Prihlásenie',
      'auth.failed': 'Neúspešné prihlásenie',
      'auth.password_reset': 'Obnova hesla',
      'queue.failed': 'Zlyhanie fronty',
      'scheduler.failed': 'Zlyhanie plánovača',
};

export const eventLabel = (event: string): string => eventLabels[event] ?? event;
