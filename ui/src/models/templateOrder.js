
import { formatDecimal } from './functions'


export default function templateOrder(data) {
  
    // data.price_sum = formatDecimal(Number(data.price_sum));
    
    // mapCase.set('statusOfSettlementOfCosts', formatCaseStatusOfSettlementOfCosts(state.case.statusOfSettlementOfCosts))
    // mapCase.set('isEditable', state.case.statusOfSettlementOfCosts !== "STORNOVANA" && state.case.statusOfSettlementOfCosts == null)
    // mapCase.set('statusOfSettlementOfCosts', state.case.statusOfSettlementOfCosts == null ? "Neuvedená" : statusOfSettlementOfCosts[state.case.statusOfSettlementOfCosts] )
    // mapCase.set('financialParticipation', state.case.financialParticipation == null ? '0.00' : formatDecimal(state.case.financialParticipation))
    // mapCase.set('lostTimeHalfHoursCountCalculator', Number(state.legalService.lostTimeHalfHoursCount * .5 || 0 ) + ' h')
  
    // mapCase.set('flatFee', formatDecimal(state.case.reimbursementSummary.flatFee))
    // mapCase.set('travelExpenses', formatDecimal(state.case.reimbursementSummary.travelExpenses))
    // mapCase.set('overheadFee', formatDecimal(state.case.reimbursementSummary.overheadFee))
    // mapCase.set('compensationForLostTime', formatDecimal(state.case.reimbursementSummary.compensationForLostTime))
    // mapCase.set('cashExpenses', formatDecimal(state.case.reimbursementSummary.cashExpenses))
    // mapCase.set('sumTotalExcludingVat', formatDecimal(state.case.reimbursementSummary.sumTotalExcludingVat))
    // mapCase.set('vatAmount', formatDecimal(state.case.reimbursementSummary.vatAmount))
    // mapCase.set('sumTotalIncludingVat', formatDecimal(state.case.reimbursementSummary.sumTotalIncludingVat))
    // mapCase.set('financialParticipation', formatDecimal(state.case.reimbursementSummary.financialParticipation))
    // mapCase.set('sumTotal', formatDecimal(state.case.reimbursementSummary.sumTotal))
    // mapCase.set('isAdvokat', profile.profile.isAdvokat)
  
    return data;
  }