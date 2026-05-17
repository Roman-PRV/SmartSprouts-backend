FROM nginx:1.27-alpine

# Bake config into image so it works in environments where the source tree
# is not present at runtime (e.g. Coolify, where the project directory and
# the build context differ).
COPY default.conf /etc/nginx/conf.d/default.conf
