/**
 * Predictive Data Asset & Inventory Sparepart Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.7 (Prediksi & Inventori Sparepart)
 * 
 * Mengelola data inventori sparepart, mutasi stok, serta parameter prediksi keandalan.
 */
export class PredictiveModel {
  /**
   * @param {Object} params
   * @param {string|number} params.id - Unique ID data prediksi & inventori
   * @param {string|number} params.aset_id - FK ke Master Aset (AssetModel)
   * @param {number} [params.sparepart_in=0] - Jumlah mutasi sparepart masuk (integer)
   * @param {number} [params.sparepart_out=0] - Jumlah mutasi sparepart keluar (integer)
   * @param {number} [params.criteria_function] - Kriteria fungsi peralatan (tinyint)
   * @param {number} [params.criteria_production_impact] - Kriteria dampak produksi (tinyint)
   * @param {number} [params.lead_time_month] - Waktu tunggu pemesanan dalam bulan (decimal)
   * @param {number} [params.sla] - Service Level Agreement, mis. 0.015 (decimal)
   * @param {string} [params.consumable_sparepart] - Jenis item: Consumable / Sparepart
   * @param {string} [params.repairable] - Status dapat diperbaiki: Y / N
   * @param {string|Date} [params.tanggal_pemasangan] - Tanggal pemasangan sparepart/peralatan (date)
   * @param {number} [params.lifetime_years] - Usia teknis / lifetime peralatan dalam tahun (integer)
   * @param {number} [params.likelihood] - Tingkat kemungkinan kegagalan (tinyint 1-4)
   * @param {number} [params.consequences] - Tingkat konsekuensi/dampak kegagalan (tinyint 1-4)
   * @param {number|string} [params.criticality] - Terhitung: Tingkat kritikalitas
   * @param {number|string} [params.lead_time_period] - Terhitung: Periode lead time
   * @param {number} [params.price] - Terhitung/input: Harga unit sparepart
   * @param {number} [params.level_inventory] - Terhitung: Target level persediaan
   * @param {number} [params.stock_saat_ini] - Terhitung: Stok fisik saat ini
   * @param {number} [params.kebutuhan] - Terhitung: Total kebutuhan sparepart
   * @param {string} [params.proposal] - Terhitung: Usulan tindak lanjut / rekomendasi
   * @param {number} [params.proposal_qty] - Terhitung: Kuantitas pengajuan usulan
   * @param {string} [params.status_kategori_qty] - Terhitung: Kategori status kuantitas stok
   * @param {number} [params.average_usage] - Terhitung: Rata-rata penggunaan per periode
   * @param {number} [params.safety_stock_based_usage] - Terhitung: Safety stock berdasarkan pemakaian
   * @param {number} [params.safety_stock_based_mca] - Terhitung: Safety stock berdasarkan MCA
   * @param {number} [params.safety_stock_based_failure] - Terhitung: Safety stock berdasarkan kegagalan
   * @param {number} [params.safety_stock] - Terhitung: Total safety stock
   * @param {number} [params.umur_peralatan_hari] - Terhitung: Umur operasional peralatan (hari)
   * @param {number} [params.umur_peralatan_tahun] - Terhitung: Umur operasional peralatan (tahun)
   * @param {number} [params.jumlah_vandalisme] - Terhitung: Total frekuensi tindak vandalisme
   * @param {number|string} [params.lifetime] - Terhitung: Sisa atau total lifetime
   * @param {number|string} [params.rating] - Terhitung: Rating risiko (likelihood x consequences)
   * @param {string} [params.concat] - Terhitung: Kode penggabungan (mis. "14")
   * @param {string} [params.desc] - Terhitung: Deskripsi level risiko (Low, Medium, High, Extreme)
   */
  constructor({
    id,
    aset_id,
    sparepart_in = 0,
    sparepart_out = 0,
    criteria_function,
    criteria_production_impact,
    lead_time_month,
    sla,
    consumable_sparepart,
    repairable,
    tanggal_pemasangan,
    lifetime_years,
    likelihood,
    consequences,
    criticality,
    lead_time_period,
    price,
    level_inventory,
    stock_saat_ini,
    kebutuhan,
    proposal,
    proposal_qty,
    status_kategori_qty,
    average_usage,
    safety_stock_based_usage,
    safety_stock_based_mca,
    safety_stock_based_failure,
    safety_stock,
    umur_peralatan_hari,
    umur_peralatan_tahun,
    jumlah_vandalisme,
    lifetime,
    rating,
    concat,
    desc
  } = {}) {
    this.id = id;
    this.aset_id = aset_id;
    this.sparepart_in = sparepart_in;
    this.sparepart_out = sparepart_out;
    this.criteria_function = criteria_function;
    this.criteria_production_impact = criteria_production_impact;
    this.lead_time_month = lead_time_month;
    this.sla = sla;
    this.consumable_sparepart = consumable_sparepart;
    this.repairable = repairable;
    this.tanggal_pemasangan = tanggal_pemasangan;
    this.lifetime_years = lifetime_years;
    this.likelihood = likelihood;
    this.consequences = consequences;
    this.criticality = criticality;
    this.lead_time_period = lead_time_period;
    this.price = price;
    this.level_inventory = level_inventory;
    this.stock_saat_ini = stock_saat_ini;
    this.kebutuhan = kebutuhan;
    this.proposal = proposal;
    this.proposal_qty = proposal_qty;
    this.status_kategori_qty = status_kategori_qty;
    this.average_usage = average_usage;
    this.safety_stock_based_usage = safety_stock_based_usage;
    this.safety_stock_based_mca = safety_stock_based_mca;
    this.safety_stock_based_failure = safety_stock_based_failure;
    this.safety_stock = safety_stock;
    this.umur_peralatan_hari = umur_peralatan_hari;
    this.umur_peralatan_tahun = umur_peralatan_tahun;
    this.jumlah_vandalisme = jumlah_vandalisme;
    this.lifetime = lifetime;
    this.rating = rating;
    this.concat = concat;
    this.desc = desc;
  }
}
