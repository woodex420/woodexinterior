import multer from 'multer';
import crypto from 'node:crypto';
import path from 'node:path';
import { ApiError } from './http.js';

export const MIME_EXT = {
  'image/jpeg': '.jpg',
  'image/png': '.png',
  'image/webp': '.webp',
  'image/avif': '.avif',
};

export function createUploader(config) {
  return multer({
    storage: multer.diskStorage({
      destination: (req, file, cb) => cb(null, config.uploadDir),
      filename: (req, file, cb) => {
        const ext = MIME_EXT[file.mimetype] || '.bin';
        cb(null, `${Date.now()}-${crypto.randomBytes(8).toString('hex')}${ext}`);
      },
    }),
    limits: { fileSize: config.maxUploadBytes, files: 1 },
    fileFilter: (req, file, cb) => {
      if (!MIME_EXT[file.mimetype]) {
        return cb(new ApiError(422, 'validation_failed', 'Only JPEG, PNG, WebP or AVIF images are allowed.', { file: 'Unsupported image type.' }));
      }
      return cb(null, true);
    },
  });
}

export function mediaName(filename) {
  return path.basename(String(filename || ''));
}
