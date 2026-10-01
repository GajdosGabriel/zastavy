
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
export const formatPrice = (number = null) => formatDecimal(number).replace('.', ',');

export const formatUnitName = (number = 0) => {
  const n = Math.abs(Number(number));
  if (n === 1) return 'kus';
  if (n >= 2 && n <= 4) return 'kusy';
  return 'kusov';
};

export const formatPriceWithoutVat = (price, vat) => {
  let result = Math.round(
    Number(price) - (Number(price) / 100) * Number(vat)
  );
  return formatDecimal(result);
};




// Veľkosť súboru pre výpis pri prílohách (KB/MB namiesto bajtov).
export const formatFileSize = (bytes = 0) => {
    const size = Number(bytes) || 0;
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(0)} kB`;
    return `${(size / 1024 / 1024).toFixed(1)} MB`;
};
