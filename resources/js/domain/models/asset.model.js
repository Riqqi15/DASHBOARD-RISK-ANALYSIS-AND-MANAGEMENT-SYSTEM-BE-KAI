/**
 * Master Asset Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.4
 * 
 * Field system dan subsystem disamakan persis dengan nama kolom
 * yang berulang di sheet Predictive Data Asset, Risk Matrix, dan Reorder Stock.
 */
export class AssetModel {
  constructor({
    id,
    unit_kerja_id,         // FK ke Unit Kerja (DAOP1, DIVRE4, dll)
    kategori_id = null,    // FK ke Kategori Aset (hierarki 8 level)
    aset_prasarana_sintel, // Kelompok besar, mis. "1. PERALATAN DALAM SINYAL ELEKTRIK"
    system,                // Mis. "Interlocking Elektrik", "Track Circuit"
    subsystem,             // Mis. "Peraga Sinyal Elektrik Utama"
    jumlah_unit = 1,       // Kolom TOTAL pada Predictive Data Asset
    tahun_pemasangan,      // Kolom Tanggal Pemasangan
    status = 'Aktif'       // Aktif / Non-aktif / Dalam perbaikan
  }) {
    this.id = id;
    this.unit_kerja_id = unit_kerja_id;
    this.kategori_id = kategori_id;
    this.aset_prasarana_sintel = aset_prasarana_sintel;
    this.system = system;
    this.subsystem = subsystem;
    this.jumlah_unit = jumlah_unit;
    this.tahun_pemasangan = tahun_pemasangan;
    this.status = status;
  }
}
