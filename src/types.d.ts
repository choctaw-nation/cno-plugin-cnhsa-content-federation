export type Options = {
	username?: string;
	app_password?: string;
	instructions?: string;
	environments?: Environment[];
};

export type Environment = 'local' | 'staging' | 'production'|'development';
