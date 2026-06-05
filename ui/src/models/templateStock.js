
import { formatDecimal, formatSubstring, formatUnitName } from './functions'
import useOrder from '../store/StoreOrders';

// const {grandTotal} = useOrder();


export default function templateStock(data) {

  data.name = formatSubstring(data.name)
  data.product_unit_value = formatUnitName(data.quantity)
  // data.quantity = data.quantity + formatUnitName(data.product_unit_value)

  return data;
}