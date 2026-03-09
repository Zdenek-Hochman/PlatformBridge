import { type ApiMiddleware } from "@/Types";

export function RetryMiddleware(retries = 2, delayMs = 0): ApiMiddleware {
	return async (__, next) => {
		let attempt = 0;

		while (attempt <= retries) {
			const result = await next();

			if (result) {
				return result;
			}

			attempt++;

			if (attempt <= retries) {
				console.warn(`[API] retry ${attempt}/${retries}`);

				if (delayMs > 0) {
					await new Promise((r) => setTimeout(r, delayMs));
				}
			}
		}

		return null;
	};
}