/**
 * Risk Matrix Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.5.1
 * Rating ringkas per subsystem
 */
export class RiskMatrixModel {
  constructor({
    id,
    aset_id,           // FK ke Master Aset
    unit_kerja_id,     // FK ke Unit Kerja (untuk filtering)
    likelihood,        // tinyint 1-4
    consequences,      // tinyint 1-4
    consequence,       // alias (agar kompatibel dengan variasi penulisan)
    rating,            // terhitung: likelihood x consequences
    concat,            // terhitung: gabungan likelihood & consequences, mis. "14"
    desc               // terhitung: Low/Medium/High/Extreme dari VLOOKUP tabel referensi
  }) {
    this.id = id;
    this.aset_id = aset_id;
    this.unit_kerja_id = unit_kerja_id;
    this.likelihood = likelihood;
    this.consequences = consequences ?? consequence;
    this.rating = rating ?? (this.likelihood * this.consequences);
    this.concat = concat ?? `${this.likelihood}${this.consequences}`;
    this.desc = desc ?? this._calculateDesc();
  }

  _calculateDesc() {
    const r = this.rating;
    if (r >= 12) return 'Extreme';
    if (r >= 8)  return 'High';
    if (r >= 4)  return 'Medium';
    return 'Low';
  }
}

/**
 * LxC / Risk Register Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.5.2
 * Detail per peristiwa risiko
 */
export class RiskRegisterModel {
  constructor({
    id,
    aset_id,
    unit_kerja_id,     // FK ke Unit Kerja (untuk filtering)
    no,
    part_number,       // Mis. SIL-01
    sub,
    peristiwa_risiko,  // Mis. "Interlocking Failure"
    penyebab_risiko,   // Mis. "PLC Failure"
    penyebab,          // Alias untuk penyebab_risiko
    dampak,
    nama_part,         // Mis. "PLC Simatic", "Q Relay/Vital Relay"
    rekomendasi,       // Rekomendasi tindakan
    likelihood,        // 1-4
    consequence,       // 1-4
    l_x_c,             // terhitung: likelihood x consequence
    status             // Open / In Progress / Closed
  }) {
    this.id = id;
    this.aset_id = aset_id;
    this.unit_kerja_id = unit_kerja_id;
    this.no = no;
    this.part_number = part_number;
    this.sub = sub;
    this.peristiwa_risiko = peristiwa_risiko;
    this.penyebab_risiko = penyebab_risiko ?? penyebab;
    this.penyebab = penyebab ?? penyebab_risiko;
    this.dampak = dampak;
    this.nama_part = nama_part;
    this.rekomendasi = rekomendasi;
    this.likelihood = likelihood;
    this.consequence = consequence;
    this.l_x_c = l_x_c ?? (likelihood && consequence ? likelihood * consequence : null);
    this.status = status;
  }
}
