/**
 * Shared Reliability Calculation Engine (Business Rules)
 * Pure domain functions for calculating RAMS metrics matching approved Excel formulas.
 */
export class ReliabilityCalculator {
  /**
   * Total Operating Hour = Operating Days * 24 * Number of Units
   */
  static calculateOperatingHours(operatingDays, numberOfUnits) {
    return operatingDays * 24 * numberOfUnits;
  }

  /**
   * Total Uptime = Total Operating Hour - Total Downtime
   */
  static calculateUptime(totalOperatingHours, totalDowntimeHours) {
    return Math.max(0, totalOperatingHours - totalDowntimeHours);
  }

  /**
   * MTBF = Total Uptime / Failure Count (IFERROR 0)
   */
  static calculateMTBF(totalUptime, failureCount) {
    if (!failureCount || failureCount <= 0) return totalUptime;
    return totalUptime / failureCount;
  }

  /**
   * Failure Rate = 1 / MTBF
   */
  static calculateFailureRate(mtbf) {
    if (!mtbf || mtbf <= 0) return 0;
    return 1 / mtbf;
  }

  /**
   * Reliability = EXP(-Failure Rate)
   */
  static calculateReliability(failureRate) {
    return Math.exp(-failureRate);
  }

  /**
   * Availability = Total Uptime / Total Operating Hour
   */
  static calculateAvailability(totalUptime, totalOperatingHours) {
    if (!totalOperatingHours || totalOperatingHours <= 0) return 0;
    return totalUptime / totalOperatingHours;
  }
}
