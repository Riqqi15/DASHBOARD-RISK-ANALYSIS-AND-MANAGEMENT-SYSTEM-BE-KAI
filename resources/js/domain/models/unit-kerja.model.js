/**
 * Unit Kerja Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.2
 * Master data wilayah kerja (Daop/Divre)
 */
export class UnitKerjaModel {
  constructor({
    id,
    kode,     // Mis. DAOP1, DAOP2, ..., DIVRE1, ..., DIVRE4
    nama,     // Mis. "Daop 1 Jakarta", "Divre III Sumatera Selatan"
    tipe,     // "Daop" / "Divre"
    status = 'Aktif' // Aktif / Non-aktif
  }) {
    this.id = id;
    this.kode = kode;
    this.nama = nama;
    this.tipe = tipe;
    this.status = status;
  }
}
