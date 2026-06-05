
export const formatSubstring = (val, stringLimit = 35) => {
  if (typeof val === "string" && val.length > stringLimit) {
    return val.substring(0, stringLimit) + '...';
  }
  return val;
};

export const formatDecimal = (number = null) => {
  if (number == null) return 0.00;
  return number.toFixed(2);
};

export const formatUnitName = (number = 0) => {
  if (number === 1) {
    return 'kus'
  } else if (number > 1 && number < 9) {
    return 'kusy';
  } else {
    return 'kusov';
  }
};

export const formatPriceWithoutVat = (price, vat) => {
  let result = Math.round(
    Number(price) - (Number(price) / 100) * Number(vat)
  );
  return formatDecimal(result);
};



