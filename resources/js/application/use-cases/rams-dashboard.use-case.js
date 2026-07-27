/**
 * Use Case: Get Dashboard Summary
 * Mengambil ringkasan data untuk halaman Dashboard Overview
 * Data difilter berdasarkan scope akun pengguna (Pusat = semua, Daop/Divre = unit_kerja sendiri)
 */
export class GetDashboardSummaryUseCase {
  constructor(ramsRepository) {
    this.ramsRepository = ramsRepository;
  }

  async execute(currentUser) {
    const unitKerjaKode = currentUser.isPusat() ? null : currentUser.unit_kerja_id;
    return await this.ramsRepository.getDashboardSummary(unitKerjaKode);
  }
}

/**
 * Use Case: Get Assets
 * Mengambil daftar Master Aset berdasarkan scope user
 */
export class GetAssetsUseCase {
  constructor(ramsRepository) {
    this.ramsRepository = ramsRepository;
  }

  async execute(currentUser) {
    const unitKerjaKode = currentUser.isPusat() ? null : currentUser.unit_kerja_id;
    return await this.ramsRepository.getAssets(unitKerjaKode);
  }
}

/**
 * Use Case: Get Risk Matrix
 * Mengambil data Risk Matrix berdasarkan scope user
 */
export class GetRiskMatrixUseCase {
  constructor(ramsRepository) {
    this.ramsRepository = ramsRepository;
  }

  async execute(currentUser) {
    const unitKerjaKode = currentUser.isPusat() ? null : currentUser.unit_kerja_id;
    return await this.ramsRepository.getRiskMatrix(unitKerjaKode);
  }
}

/**
 * Use Case: Get Reliability Data
 * Mengambil data Keandalan berdasarkan scope user
 */
export class GetReliabilityDataUseCase {
  constructor(ramsRepository) {
    this.ramsRepository = ramsRepository;
  }

  async execute(currentUser) {
    const unitKerjaKode = currentUser.isPusat() ? null : currentUser.unit_kerja_id;
    return await this.ramsRepository.getReliabilityData(unitKerjaKode);
  }
}

/**
 * Use Case: Get Failure Logs
 * Mengambil Log Kejadian Kegagalan berdasarkan reliability_id
 */
export class GetFailureLogsUseCase {
  constructor(ramsRepository) {
    this.ramsRepository = ramsRepository;
  }

  async execute(reliabilityId) {
    return await this.ramsRepository.getFailureLogs(reliabilityId);
  }
}
