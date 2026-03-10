import { type ApiMiddleware, type ApiResult } from "assets/ts/Types";

export function CacheMiddleware(ttl = 10_000): ApiMiddleware {
    const cache = new Map<string, { data: ApiResult; expire: number }>();

    return async (ctx, next) => {
        const key = JSON.stringify(ctx.payload);
        const now = Date.now();
        const cached = cache.get(key);

        if (cached && cached.expire > now) {
            return cached.data;
        }

        const result = await next();

        if (result) {
            cache.set(key, { data: result, expire: now + ttl });
        }

        return result;
    };
}