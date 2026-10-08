// Slovenské názvy udalostí denníka (kód `kanál.udalosť` zapisuje App\Services\SystemLog\Recorder).
const eventLabels: Record<string, string> = {
      'order.created': 'Vytvorenie objednávky',
      'order.updated': 'Úprava objednávky',
      'order.cancelled': 'Stornovanie objednávky',
      'order.deleted': 'Odstránenie objednávky',
      'order.restored': 'Obnovenie objednávky',
      'order.delivery_changed': 'Zmena doručovacej adresy',
      'order.price_changed': 'Zmena ceny alebo zľavy',
      'order.item_added': 'Pridanie položky',
      'order.item_updated': 'Úprava položky',
      'order.item_deleted': 'Odstránenie položky',
      'order.item_restored': 'Obnovenie položky',
      'order.prepared': 'Príprava zásielky',
      'order.dispatched': 'Expedícia zásielky',
      'order.preparation_cancelled': 'Zrušenie prípravy zásielky',
      'order.return_created': 'Vytvorenie vratky',
      'order.return_updated': 'Úprava vratky',
      'order.return_items_updated': 'Úprava položiek vratky',
      'order.return_processed': 'Vybavenie vratky',
      'order.return_cancelled': 'Zrušenie vratky',
      'order.return_deleted': 'Odstránenie vratky',
      'order.return_restored': 'Obnovenie vratky',
      'quote.accepted': 'Prijatie cenovej ponuky',
      'stock.created': 'Vytvorenie skladového pohybu',
      'stock.updated': 'Úprava skladového pohybu',
      'stock.deleted': 'Odstránenie skladového pohybu',
      'stock.restored': 'Obnovenie skladového pohybu',
      'customer.merged': 'Zlúčenie zákazníkov',
      'user.roles_changed': 'Zmena rolí používateľa',
      'user.status_changed': 'Zmena stavu používateľa',
      'user.activated': 'Aktivácia používateľa',
      'user.deactivated': 'Deaktivácia používateľa',
      'user.deleted': 'Odstránenie používateľa',
      'user.restored': 'Obnovenie používateľa',
      'mail.sent': 'E-mail odoslaný',
      'mail.failed': 'E-mail zlyhal',
      'auth.login': 'Prihlásenie',
      'auth.failed': 'Neúspešné prihlásenie',
      'auth.password_reset': 'Obnova hesla',
      'queue.failed': 'Zlyhanie fronty',
      'scheduler.failed': 'Zlyhanie plánovača',
};

// Šablóny e-mailov podľa triedy notifikácie (context.class).
const mailTemplates: Record<string, { label: string; description: string }> = {
      OrderCreated: { label: 'Potvrdenie objednávky', description: 'Odchádza zákazníkovi aj obchodu hneď po vytvorení objednávky.' },
      OrderUpdated: { label: 'Zmena objednávky', description: 'Odchádza po úprave položiek alebo údajov objednávky.' },
      OrderCancelled: { label: 'Storno objednávky', description: 'Odchádza zákazníkovi po stornovaní objednávky.' },
      OrderDeliveryAddressChanged: { label: 'Zmena adresy doručenia', description: 'Odchádza po zmene doručovacej adresy objednávky.' },
      OrderPreparing: { label: 'Objednávka sa pripravuje', description: 'Odchádza zákazníkovi, keď sklad začne pripravovať zásielku.' },
      OrderExpedition: { label: 'Expedícia objednávky', description: 'Odchádza po odoslaní zásielky (aj čiastočnej).' },
      OrderReturnProcessed: { label: 'Vybavenie vratky', description: 'Odchádza zákazníkovi po vybavení vratky.' },
      CouponIssued: { label: 'Zľavový kupón', description: 'Odchádza zákazníkovi s kupónom na ďalší nákup.' },
      CustomerReviewDigest: { label: 'Prehľad zákazníkov na kontrolu', description: 'Súhrnný e-mail pre obchod.' },
      ResetPassword: { label: 'Obnovenie hesla', description: 'Odkaz na nastavenie nového hesla.' },
      UserInvited: { label: 'Pozvánka používateľa', description: 'Prístupové údaje k novému účtu.' },
};

export const mailTemplate = (className?: string | null): { label: string; description: string } => {
      const name = (className ?? '').split('\\').pop() ?? '';
      return mailTemplates[name] ?? { label: name || 'Vlastný e-mail', description: name ? '' : 'E-mail bez šablóny notifikácie (napr. kampaň alebo testovací e-mail).' };
};

export const eventLabel =(event: string): string => eventLabels[event] ?? event;
