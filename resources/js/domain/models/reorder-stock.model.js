/**
 * Reorder Stock Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.8 (Reorder Stock)
 * 
 * Mengelola data reorder point dan safety stock untuk peralatan/equipment prasarana sintel.
 */
export class ReorderStockModel {
  /**
   * @param {Object} params
   * @param {string|number} params.id - Unique ID Reorder Stock
   * @param {string|number} params.aset_id - FK ke Master Aset (System & Sub-System diambil dari aset ini)
   * @param {string} [params.equipment] - Nama peralatan / equipment (varchar)
   * @param {string} [params.detail_equipment] - Detail spesifikasi equipment (varchar)
   * @param {number} [params.max_yearly_failure] - Frekuensi kegagalan tahunan maksimal (integer)
   * @param {number} [params.average_yearly_failure] - Rata-rata kegagalan tahunan (decimal)
   * @param {number} [params.max_lead_time_month] - Lead time maksimal dalam bulan (decimal)
   * @param {number} [params.average_lead_time_month] - Rata-rata lead time dalam bulan (decimal)
   * @param {number} [params.safety_stock] - Terhitung: Max Yearly Failure x Max Lead Time - Average Yearly Failure x Average Lead Time
   * @param {number} [params.lead_time_demand] - Terhitung: Average Lead Time x Average Yearly Failure
   * @param {number} [params.reorder_point] - Terhitung: Lead Time Demand + Safety Stock
   * @param {string} [params.severity_equipment] - Tingkat keparahan dampak peralatan (varchar)
   */
  constructor({
    id,
    aset_id,
    equipment,
    detail_equipment,
    max_yearly_failure,
    average_yearly_failure,
    max_lead_time_month,
    average_lead_time_month,
    safety_stock,
    lead_time_demand,
    reorder_point,
    severity_equipment
  } = {}) {
    this.id = id;
    this.aset_id = aset_id;
    this.equipment = equipment;
    this.detail_equipment = detail_equipment;
    this.max_yearly_failure = max_yearly_failure;
    this.average_yearly_failure = average_yearly_failure;
    this.max_lead_time_month = max_lead_time_month;
    this.average_lead_time_month = average_lead_time_month;

    // Safety Stock = (Max Yearly Failure x Max Lead Time) - (Average Yearly Failure x Average Lead Time)
    const computedSafetyStock = (max_yearly_failure != null && max_lead_time_month != null && average_yearly_failure != null && average_lead_time_month != null)
      ? (max_yearly_failure * max_lead_time_month) - (average_yearly_failure * average_lead_time_month)
      : undefined;
    this.safety_stock = safety_stock ?? computedSafetyStock;

    // Lead Time Demand = Average Lead Time x Average Yearly Failure
    const computedLeadTimeDemand = (average_lead_time_month != null && average_yearly_failure != null)
      ? (average_lead_time_month * average_yearly_failure)
      : undefined;
    this.lead_time_demand = lead_time_demand ?? computedLeadTimeDemand;

    // Reorder Point = Lead Time Demand + Safety Stock
    const computedReorderPoint = (this.lead_time_demand != null && this.safety_stock != null)
      ? (this.lead_time_demand + this.safety_stock)
      : undefined;
    this.reorder_point = reorder_point ?? computedReorderPoint;

    this.severity_equipment = severity_equipment;
  }
}
