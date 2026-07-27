/**
 * Get Master Assets Use Case
 * Encapsulates the application logic for retrieving assets based on user authorization.
 */
export class GetAssetsUseCase {
  constructor(assetRepository) {
    this.assetRepository = assetRepository;
  }

  async execute(currentUserScope) {
    if (!currentUserScope) {
      throw new Error('Unauthenticated');
    }
    return await this.assetRepository.getAll(currentUserScope);
  }
}
