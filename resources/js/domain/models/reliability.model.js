/**
 * Data Keandalan (Reliability) Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.6.1
 * Ringkasan per subsystem, per periode
 */
export class ReliabilityModel {
  constructor({
    id,
    aset_id,
    unit_kerja_id,                    // FK ke Unit Kerja (untuk filtering)
    periode,                          // Bulan/tahun pencatatan
    jumlah_unit,
    total_operating_hour,             // = jumlah_hari_periode x 24 x jumlah_unit
    total_downtime,                   // = SUM(konversi_ke_menit) dari Log Kejadian
    total_uptime,                     // = total_operating_hour - total_downtime
    jumlah_failure,                   // = COUNT baris Log Kejadian
    mttf,                             // = AVERAGE(interval antar failure)
    mtbf,                             // = total_uptime / jumlah_failure
    mttr,                             // Mean Time To Repair (jam)
    failure_rate,                     // = 1 / mtbf
    reliability,                      // = EXP(-failure_rate)
    availability,                     // = total_uptime / total_operating_hour
    jumlah_penggantian_sparepart,     // = COUNT Log dengan Penggantian Sparepart = "Ya"
    jumlah_tindak_vandalisme          // = COUNT Log dengan Tindak Vandalisme = "Ya"
  }) {
    this.id = id;
    this.aset_id = aset_id;
    this.unit_kerja_id = unit_kerja_id;
    this.periode = periode;
    this.jumlah_unit = jumlah_unit;
    this.total_operating_hour = total_operating_hour;
    this.total_downtime = total_downtime;
    this.total_uptime = total_uptime;
    this.jumlah_failure = jumlah_failure;
    this.mttf = mttf;
    this.mtbf = mtbf;
    this.mttr = mttr;
    this.failure_rate = failure_rate;
    this.reliability = reliability;
    this.availability = availability;
    this.jumlah_penggantian_sparepart = jumlah_penggantian_sparepart;
    this.jumlah_tindak_vandalisme = jumlah_tindak_vandalisme;
  }
}

/**
 * Log Kejadian Kegagalan Domain Model
 * Sesuai Dokumen Database RAMS FINAL - Modul 1.6.2
 * Child table, per kejadian
 */
export class FailureLogModel {
  constructor({
    id,
    reliability_id,        // FK ke Data Keandalan
    lokasi,
    resor,
    qc,
    failure_event,         // Nama/deskripsi kejadian kegagalan
    deskripsi_kegagalan,   // Alias untuk failure_event
    penyebab,
    tindakan,
    penggantian_sparepart, // Y/N
    tindak_vandalisme,     // Y/N
    tanggal_kejadian,
    tanggal,               // Alias untuk tanggal_kejadian
    mulai,                 // Jam mulai gangguan
    tanggal_penanganan,
    selesai,               // Jam selesai penanganan
    downtime_jam,          // terhitung
    durasi_penanganan,     // Alias untuk downtime_jam (dalam jam)
    konversi_ke_menit,     // terhitung
    interval_antar_failure_jam, // terhitung
    tahun_kejadian         // terhitung
  }) {
    this.id = id;
    this.reliability_id = reliability_id;
    this.lokasi = lokasi;
    this.resor = resor;
    this.qc = qc;
    this.failure_event = failure_event ?? deskripsi_kegagalan;
    this.deskripsi_kegagalan = deskripsi_kegagalan ?? failure_event;
    this.penyebab = penyebab;
    this.tindakan = tindakan;
    this.penggantian_sparepart = penggantian_sparepart;
    this.tindak_vandalisme = tindak_vandalisme;
    this.tanggal_kejadian = tanggal_kejadian ?? tanggal;
    this.tanggal = tanggal ?? tanggal_kejadian;
    this.mulai = mulai;
    this.tanggal_penanganan = tanggal_penanganan;
    this.selesai = selesai;
    this.downtime_jam = downtime_jam ?? durasi_penanganan;
    this.durasi_penanganan = durasi_penanganan ?? downtime_jam;
    this.konversi_ke_menit = konversi_ke_menit;
    this.interval_antar_failure_jam = interval_antar_failure_jam;
    this.tahun_kejadian = tahun_kejadian;
  }
}
