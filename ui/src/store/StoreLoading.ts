import { createRequestActivity } from '../models/httpActivity';

// Zdieľaný stav pre axios aj komponenty; prekrývajúce sa requesty sa počítajú.
export default createRequestActivity();
