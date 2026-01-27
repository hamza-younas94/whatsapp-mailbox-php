"use strict";
// src/middleware/validation.middleware.ts
// Input validation using Zod
Object.defineProperty(exports, "__esModule", { value: true });
exports.validate = validate;
exports.validateRequest = validate;
exports.validateQuery = validateQuery;
const errors_1 = require("../utils/errors");
function validate(schema) {
    return (req, _res, next) => {
        try {
            const validated = schema.parse(req.body);
            req.body = validated;
            next();
        }
        catch (error) {
            const message = error.errors?.map((e) => `${e.path.join('.')}: ${e.message}`).join('; ');
            throw new errors_1.ValidationError(message || 'Validation failed');
        }
    };
}
function validateQuery(schema) {
    return (req, _res, next) => {
        try {
            const validated = schema.parse(req.query);
            req.query = validated;
            next();
        }
        catch (error) {
            const message = error.errors?.map((e) => `${e.path.join('.')}: ${e.message}`).join('; ');
            throw new errors_1.ValidationError(message || 'Validation failed');
        }
    };
}
//# sourceMappingURL=validation.middleware.js.map