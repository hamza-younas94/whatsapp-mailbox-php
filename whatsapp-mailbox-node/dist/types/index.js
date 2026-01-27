"use strict";
// src/types/index.ts
// Core type definitions following Domain-Driven Design
Object.defineProperty(exports, "__esModule", { value: true });
exports.UserRole = exports.MessageDirection = exports.MessageStatus = exports.MediaType = exports.MessageType = void 0;
var MessageType;
(function (MessageType) {
    MessageType["TEXT"] = "TEXT";
    MessageType["IMAGE"] = "IMAGE";
    MessageType["VIDEO"] = "VIDEO";
    MessageType["AUDIO"] = "AUDIO";
    MessageType["DOCUMENT"] = "DOCUMENT";
})(MessageType || (exports.MessageType = MessageType = {}));
var MediaType;
(function (MediaType) {
    MediaType["IMAGE"] = "image";
    MediaType["VIDEO"] = "video";
    MediaType["AUDIO"] = "audio";
    MediaType["DOCUMENT"] = "document";
})(MediaType || (exports.MediaType = MediaType = {}));
var MessageStatus;
(function (MessageStatus) {
    MessageStatus["PENDING"] = "PENDING";
    MessageStatus["SENT"] = "SENT";
    MessageStatus["DELIVERED"] = "DELIVERED";
    MessageStatus["READ"] = "READ";
    MessageStatus["FAILED"] = "FAILED";
})(MessageStatus || (exports.MessageStatus = MessageStatus = {}));
var MessageDirection;
(function (MessageDirection) {
    MessageDirection["INCOMING"] = "INCOMING";
    MessageDirection["OUTGOING"] = "OUTGOING";
})(MessageDirection || (exports.MessageDirection = MessageDirection = {}));
var UserRole;
(function (UserRole) {
    UserRole["ADMIN"] = "ADMIN";
    UserRole["MANAGER"] = "MANAGER";
    UserRole["AGENT"] = "AGENT";
    UserRole["USER"] = "USER";
})(UserRole || (exports.UserRole = UserRole = {}));
//# sourceMappingURL=index.js.map