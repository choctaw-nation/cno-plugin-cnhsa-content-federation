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
