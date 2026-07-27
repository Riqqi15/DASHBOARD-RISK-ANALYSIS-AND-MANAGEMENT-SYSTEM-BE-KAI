/**
 * Kategori Aset (Hierarki 8 Level) Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.3
 * 
 * Merepresentasikan struktur berjenjang pada sheet Sheet1.
 * Level 1=Kelompok Peralatan, 2=Sub Kelompok Peralatan, 
 * 3=Detail Peralatan, 4-8=Level 3 s.d. Level 7
 */
export class KategoriAsetModel {
  constructor({
    id,
    parent_id = null,  // FK self-relation untuk membentuk hierarki
    level,             // tinyint 1-8
    nama,              // Nama kategori sesuai levelnya
    sat = null,        // Kolom SAT (satuan: unit, set, dsb.)
    qty = 0,           // Kolom QTY
    total_qty = 0      // Kolom TOTAL QTY — rekap jumlah pada level tersebut
  }) {
    this.id = id;
    this.parent_id = parent_id;
    this.level = level;
    this.nama = nama;
    this.sat = sat;
    this.qty = qty;
    this.total_qty = total_qty;
  }
}
