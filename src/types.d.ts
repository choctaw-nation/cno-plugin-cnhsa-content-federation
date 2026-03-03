export type Environment = 'local' | 'staging' | 'production' | 'development';

export type Credential = {
	username?: string;
	app_password?: string;
};

export type Options = {
	instructions?: string;
	environments?: Environment[];
	localUrl?: string;
	credentials?: Partial<Record<Environment, Credential>>;
};
declare global {
	/**
	 * Also expose as a top-level const for modules that reference it directly.
	 * This mirrors the inline script added in PHP and keeps the type-safe shape.
	 */
	const cnhsaFederationSettings: Readonly<{
		environment: Environment;
	}>;
}
export {}
