/**
 * Tabel Referensi (Master Kriteria) Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.9
 * 
 * Sheet "Condition" yang berfungsi sebagai master lookup
 * dipakai oleh banyak sheet lain lewat VLOOKUP.
 */
export class TabelReferensiModel {
  constructor({
    id,
    tipe_lookup,       // Mis. "function_impact_to_criticality", "likelihood_consequence_to_rating"
    kombinasi_input,   // Nilai kombinasi kunci pencarian, mis. "11" 
    hasil,             // Nilai hasil, mis. "Dessirable", "More Pieces in Stock", "Low"
    urutan = 0         // Urutan tampil/prioritas
  }) {
    this.id = id;
    this.tipe_lookup = tipe_lookup;
    this.kombinasi_input = kombinasi_input;
    this.hasil = hasil;
    this.urutan = urutan;
  }
}
