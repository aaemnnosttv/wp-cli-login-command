eval "$(ssh-agent -s)" #start the ssh agent
chmod 600 deploy/key
ssh-add deploy/key

git subsplit init git@github.com:aaemnnosttv/wp-cli-login-command.git

git subsplit publish --heads="$TRAVIS_BRANCH" plugin/:git@github.com:aaemnnosttv/wp-cli-login-server.git
