"use strict";
// src/utils/auth-helpers.ts
// Auth utility functions
Object.defineProperty(exports, "__esModule", { value: true });
exports.getUserId = getUserId;
exports.requireUserId = requireUserId;
/**
 * Get userId from authenticated request
 * Supports both old tokens (userId only) and new tokens (id + userId)
 */
function getUserId(req) {
    return req.user?.id || req.user?.userId;
}
/**
 * Get userId and throw if not found
 */
function requireUserId(req) {
    const userId = getUserId(req);
    if (!userId) {
        throw new Error('User ID not found in request. Please logout and login again.');
    }
    return userId;
}
//# sourceMappingURL=auth-helpers.js.map