import { type ApiMiddleware } from "@/Types";

export const TimingMiddleware: ApiMiddleware = async (context, next) => {
    const start = performance.now();
    const result = await next();
    const duration = performance.now() - start;
    console.log(`[API] ${context.transport ?? 'unknown'}: ${duration.toFixed(1)}ms`);
    return result;
};