
export const formatSubstring = (val, stringLimit = 35) => {
  if (typeof val === "string" && val.length > stringLimit) {
    return val.substring(0, stringLimit) + '...';
  }
  return val;
};

export const formatDecimal = (number = null) => {
  if (number == null) return '0.00';
  return Number(number).toFixed(2);
};

// Cena na zobrazenie: slovenská desatinná čiarka (23,00). Na hodnoty vo formulároch ostáva formatDecimal.
const priceFormatter = new Intl.NumberFormat('sk-SK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const formatPrice = (number = null) => priceFormatter.format(Number(number ?? 0));

export const formatUnitName = (number = 0) => {
  const n = Math.abs(Number(number));
  if (n === 1) return 'kus';
  if (n >= 2 && n <= 4) return 'kusy';
  return 'kusov';
};

// Slovenské skloňovanie podľa počtu: 1 objednávka, 2–4 objednávky, 0 a 5+ objednávok.
export const plural = (count, one, few, many) => {
  const n = Math.abs(Number(count) || 0);
  if (n === 1) return one;
  if (n >= 2 && n <= 4) return few;
  return many;
};

// Dátum na zobrazenie (28. 9. 2026). Čisté Y-m-d sa nepreráta cez časové pásmo, ISO čas áno.
export const formatDate = (value) => {
  if (!value) return '';
  const plain = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value));
  if (plain) return `${Number(plain[3])}. ${Number(plain[2])}. ${plain[1]}`;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('sk-SK');
};

export const formatPriceWithoutVat = (price, vat) => {
  return formatDecimal(Number(price) / (1 + Number(vat) / 100));
};




// Veľkosť súboru pre výpis pri prílohách (KB/MB namiesto bajtov).
export const formatFileSize = (bytes = 0) => {
    const size = Number(bytes) || 0;
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(0)} kB`;
    return `${(size / 1024 / 1024).toFixed(1)} MB`;
};
